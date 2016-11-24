<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/11/16 10:19 PM
*/

namespace Youshido\GraphQL\Type;


use Youshido\GraphQL\Type\Enum\AbstractEnumType;
use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\ListType\AbstractListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\AbstractScalarType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TypeService
{

    const TYPE_CALLABLE               = 'callable';
    const TYPE_GRAPHQL_TYPE           = 'graphql_type';
    const TYPE_OBJECT_TYPE            = 'object_type';
    const TYPE_ARRAY_OF_OBJECT_TYPES  = 'array_of_object_types';
    const TYPE_OBJECT_INPUT_TYPE      = 'object_input_type';
    const TYPE_LIST                   = 'list';
    const TYPE_BOOLEAN                = TypeMap::TYPE_BOOLEAN;
    const TYPE_STRING                 = TypeMap::TYPE_STRING;
    const TYPE_ARRAY                  = 'array';
    const TYPE_ARRAY_OF_FIELDS_CONFIG = 'array_of_fields';
    const TYPE_ARRAY_OF_INPUT_FIELDS  = 'array_of_inputs';
    const TYPE_ENUM_VALUES            = 'array_of_values';
    const TYPE_ARRAY_OF_INTERFACES    = 'array_of_interfaces';
    const TYPE_ANY                    = 'any';
    const TYPE_ANY_OBJECT             = 'any_object';
    const TYPE_ANY_INPUT              = 'any_input';

    public static function resolveNamedType($object)
    {
        if (is_object($object)) {
            if ($object instanceof AbstractType) {
                return $object->getType();
            }
        } elseif (is_null($object)) {
            return null;
        } elseif (is_scalar($object)) {
            return new StringType();
        }

        throw new \Exception('Invalid type');
    }

    /**
     * @param AbstractType|mixed $type
     * @return bool
     */
    public static function isInterface($type)
    {
        if (!is_object($type)) {
            return false;
        }

        return $type->getKind() == TypeMap::KIND_INTERFACE;
    }

    /**
     * @param AbstractType|mixed $type
     * @return bool
     */
    public static function isAbstractType($type)
    {
        if (!is_object($type)) {
            return false;
        }

        return in_array($type->getKind(), [TypeMap::KIND_INTERFACE, TypeMap::KIND_UNION]);
    }

    public static function isScalarType($type)
    {
        if (is_object($type)) {
            return $type instanceof AbstractScalarType || $type instanceof AbstractEnumType;
        }

        return in_array(strtolower($type), TypeFactory::getScalarTypesNames());
    }

    public static function isGraphQLType($type)
    {
        return $type instanceof AbstractType || TypeService::isScalarType($type);
    }

    public static function isLeafType($type)
    {
        return $type instanceof AbstractEnumType || TypeService::isScalarType($type);
    }

    public static function isObjectType($type)
    {
        return $type instanceof AbstractObjectType;
    }

    /**
     * @param mixed|AbstractType $type
     * @return bool
     */
    public static function isInputType($type)
    {
        if (is_object($type)) {
            $namedType = $type->getNullableType()->getNamedType();

            return ($namedType instanceof AbstractScalarType)
                   || ($type instanceof AbstractListType)
                   || ($namedType instanceof AbstractInputObjectType)
                   || ($namedType instanceof AbstractEnumType);
        } else {
            return TypeService::isScalarType($type);
        }
    }

    public static function isInputObjectType($type)
    {
        return $type instanceof AbstractInputObjectType;
    }

    public static function getPropertyValue($data, $path)
    {
        if (is_object($data)) {
            $getter = $path;
            if (substr($path, 0, 2) != 'is') {
                $getter = 'get' . self::classify($path);
            }

            return is_callable([$data, $getter]) ? $data->$getter() : (isset($data->$path) ? $data->$path : null);
        } elseif (is_array($data)) {
            return array_key_exists($path, $data) ? $data[$path] : null;
        }

        return null;
    }

    protected static function classify($text)
    {
        $text       = explode(' ', str_replace(['_', '/', '-', '.'], ' ', $text));
        $textLength = count($text);
        for ($i = 0; $i < $textLength; $i++) {
            $text[$i] = ucfirst($text[$i]);
        }
        $text = ucfirst(implode('', $text));

        return $text;
    }
}
