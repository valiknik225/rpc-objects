<?php

namespace Ufo\RpcObject\DTO;

use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use ReflectionException;
use Ufo\RpcObject\Helpers\TypeHintResolver;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\Rules\Validator\Validator;

use function is_null;

class DTOTransformer
{
    /**
     * Converts a DTO object to an associative array.
     *
     * @param object $dto The object to convert.
     * @return array An associative array of the object's properties.
     */
    public static function toArray(object $dto): array
    {
        $reflection = new ReflectionClass($dto);
        $properties = $reflection->getProperties();
        $array = [];

        foreach ($properties as $property) {
            $value = $property->getValue($dto);
            $value = static::convertValue($value);
            $array[$property->getName()] = $value;
        }

        return $array;
    }

    protected static function convertValue(mixed $value): mixed
    {
        return match (gettype($value)) {
            TypeHintResolver::ARRAY->value => static::mapArrayWithKeys($value),
            TypeHintResolver::OBJECT->value => $value instanceof IArrayConvertible ? $value->toArray() : static::toArray($value),
            default => $value,
        };
    }

    protected static function mapArrayWithKeys(array $array): array
    {
        $result = [];
        foreach ($array as $k => $v) {
            $result[$k] = static::convertValue($v);
        }
        return $result;
    }

    /**
     * Creates a DTO object from an associative array.
     *
     * @param string $classFQCN The name of the class to instantiate.
     * @param array $data The array of data to populate the object.
     * @return object The created object.
     * @throws ReflectionException|InvalidArgumentException|RpcBadParamException
     */
    public static function fromArray(string $classFQCN, array $data): object
    {
        $reflection = new ReflectionClass($classFQCN);
        $instance = $reflection->newInstanceWithoutConstructor();

        foreach ($reflection->getProperties() as $property) {
            $key = $property->getName();

            if (!isset($data[$key]) && !is_null($data[$key])) {
                if (!$property->hasDefaultValue()) {
                    throw new InvalidArgumentException("Missing required key: '$key'");
                }
                continue;
            }

            self::validateProperty($property, $data[$key]);

            $property->setValue($instance, $data[$key]);
        }

        return $instance;
    }

    /**
     * @throws RpcBadParamException
     */
    private static function validateProperty(ReflectionProperty $property, mixed $value): void
    {
        $attributes = $property->getAttributes(Assertions::class);

        if (!empty($attributes)) {
            $assertions = $attributes[0]->newInstance()->assertions;
            $validator = Validator::validate($value, $assertions);

            if ($validator->hasErrors()) {
                $errorMessage = $property->getName() .  $validator->getCurrentError();
                throw new RpcBadParamException($errorMessage);
            }
        }
    }
}