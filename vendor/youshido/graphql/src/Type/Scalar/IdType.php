<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/4/15 12:41 AM
*/

namespace Youshido\GraphQL\Type\Scalar;


class IdType extends AbstractScalarType
{

    public function getName()
    {
        return 'ID';
    }

    public function serialize($value)
    {
        return (string)$value;
    }

    public function getDescription()
    {
        return 'The `ID` scalar type represents a unique identifier, often used to ' .
               'refetch an object or as key for a cache. The ID type appears in a JSON ' .
               'response as a String; however, it is not intended to be human-readable. ' .
               'When expected as an input type, any string (such as `"4"`) or integer ' .
               '(such as `4`) input value will be accepted as an ID.';
    }
}
