<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/11/16 9:19 PM
*/

namespace Youshido\GraphQL\Type;


use Youshido\GraphQL\Type\Scalar\AbstractScalarType;
use Youshido\GraphQL\Validator\Exception\ConfigurationException;

class TypeFactory
{
    private static $objectsHash = [];

    /**
     * @param string $type
     *
     * @throws ConfigurationException
     * @return AbstractScalarType
     */
    public static function getScalarType($type)
    {
        if (TypeService::isScalarType($type)) {
            if (is_object($type)) {
                $typeName = $type->getName();
                if (empty(self::$objectsHash[$typeName])) {
                    self::$objectsHash[$typeName] = $type;
                }
                return self::$objectsHash[$typeName];
            }
            if (empty(self::$objectsHash[$type])) {
                $name = ucfirst($type);

                $name = $name == 'Datetime' ? 'DateTime' : $name;
                $name = $name == 'Datetimetz' ? 'DateTimeTz' : $name;

                $className                = 'Youshido\GraphQL\Type\Scalar\\' . $name . 'Type';
                self::$objectsHash[$type] = new $className();
            }

            return self::$objectsHash[$type];
        } else {
            throw new ConfigurationException('Configuration problem with type ' . $type);
        }
    }

    /**
     * @return string[]
     */
    public static function getScalarTypesNames()
    {
        return [
            TypeMap::TYPE_INT,
            TypeMap::TYPE_FLOAT,
            TypeMap::TYPE_STRING,
            TypeMap::TYPE_BOOLEAN,
            TypeMap::TYPE_ID,
            TypeMap::TYPE_DATETIME,
            TypeMap::TYPE_DATE,
            TypeMap::TYPE_TIMESTAMP,
            TypeMap::TYPE_DATETIMETZ,
        ];
    }
}
