<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 3:40 PM 4/29/16
 */

namespace Youshido\GraphQL\Type;


use Youshido\GraphQL\Config\Traits\ConfigAwareTrait;
use Youshido\GraphQL\Validator\Exception\ConfigurationException;

final class NonNullType extends AbstractType implements CompositeTypeInterface
{
    use ConfigAwareTrait;

    private $_typeOf;

    /**
     * NonNullType constructor.
     *
     * @param AbstractType|string $fieldType
     *
     * @throws ConfigurationException
     */
    public function __construct($fieldType)
    {
        if (!TypeService::isGraphQLType($fieldType)) {
            throw new ConfigurationException('NonNullType accepts only GraphpQL Types as argument');
        }
        if (TypeService::isScalarType($fieldType)) {
            $fieldType = TypeFactory::getScalarType($fieldType);
        }

        $this->_typeOf = $fieldType;
    }

    public function getName()
    {
        return null;
    }

    public function getKind()
    {
        return TypeMap::KIND_NON_NULL;
    }

    public function resolve($value)
    {
        return $value;
    }

    public function isValidValue($value)
    {
        if ($value === null) {
            return false;
        }

        return $this->getNullableType()->isValidValue($value);
    }

    public function isCompositeType()
    {
        return true;
    }

    public function getNamedType()
    {
        return $this->getTypeOf();
    }

    public function getNullableType()
    {
        return $this->getTypeOf();
    }

    public function getTypeOf()
    {
        return $this->_typeOf;
    }

    public function parseValue($value)
    {
        return $this->getNullableType()->parseValue($value);
    }


}
