<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/3/15 10:10 PM
*/

namespace Youshido\GraphQL\Type\Scalar;

class FloatType extends AbstractScalarType
{

    public function getName()
    {
        return 'Float';
    }

    public function serialize($value)
    {
        if ($value === null) {
            return null;
        } else {
            return floatval($value);
        }
    }

    public function isValidValue($value)
    {
        return is_null($value) || is_float($value) || is_int($value);
    }

    public function getDescription()
    {
        return 'The `Float` scalar type represents signed double-precision fractional ' .
               'values as specified by ' .
               '[IEEE 754](http://en.wikipedia.org/wiki/IEEE_floating_point).';
    }

}
