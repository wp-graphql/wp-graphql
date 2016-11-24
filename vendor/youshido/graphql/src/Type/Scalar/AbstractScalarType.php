<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/27/15 1:00 AM
*/

namespace Youshido\GraphQL\Type\Scalar;

use Youshido\GraphQL\Config\Traits\ConfigAwareTrait;
use Youshido\GraphQL\Type\AbstractType;
use Youshido\GraphQL\Type\TypeMap;

abstract class AbstractScalarType extends AbstractType
{
    use ConfigAwareTrait;

    public function getName()
    {
        $className = get_class($this);

        return substr($className, strrpos($className, '\\') + 1, -4);
    }

    final public function getKind()
    {
        return TypeMap::KIND_SCALAR;
    }

    public function parseValue($value)
    {
        return $this->serialize($value);
    }

    public function isInputType()
    {
        return true;
    }


}
