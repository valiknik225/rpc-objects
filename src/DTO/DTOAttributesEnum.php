<?php

namespace Ufo\RpcObject\DTO;

use Attribute;
use ReflectionProperty;
use Ufo\RpcError\RpcBadParamException;
use Ufo\RpcObject\RPC\Assertions;
use Ufo\RpcObject\RPC\DTO;
use Ufo\RpcObject\RPC\ResultAsDTO;
use Ufo\RpcObject\Rules\Validator\Validator;

enum DTOAttributesEnum: string
{
    case ASSERTIONS = Assertions::class;
    case DTO = DTO::class;

    public function process(object $attribute, mixed $value, ReflectionProperty $property): mixed
    {
        return match ($this) {
            self::ASSERTIONS => $this->validate($attribute, $value, $property),
            self::DTO => $this->resolveDTO($attribute, $value, $property),
            default => $value,
        };
    }

    protected function resolveDTO(DTO $attribute, mixed $value, ReflectionProperty $property): array|object
    {
        if ($attribute->collection) {
            return $this->transformDTOCollection($attribute, $value, $property);
        }
        return $this->transformDto($attribute, $value, $property);
    }

    protected function transformDTOCollection(DTO $attribute, mixed $value, ReflectionProperty $property): array
    {
        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = $this->transformDto($attribute, $item, $property);
        }
        return $result;
    }

    protected function transformDto(DTO $attribute, mixed $value, ReflectionProperty $property): object
    {
        return DTOTransformer::fromArray($attribute->dtoFQCN, $value);
    }

    protected function validate(Assertions $attribute, mixed $value, ReflectionProperty $property): mixed
    {
        $assertions = $attribute->assertions;
        $validator = Validator::validate($value, $assertions);

        if ($validator->hasErrors()) {
            $errorMessage = $property->getName() . $validator->getCurrentError();
            throw new RpcBadParamException($errorMessage);
        }
        return $value;
    }
}
