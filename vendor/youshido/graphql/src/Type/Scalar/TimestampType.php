<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/27/15 1:22 AM
*/

namespace Youshido\GraphQL\Type\Scalar;


class TimestampType extends AbstractScalarType
{

    public function getName()
    {
        return 'Timestamp';
    }

    /**
     * @param $value \DateTime
     * @return null|string
     */
    public function serialize($value)
    {
        if ($value === null || !is_object($value)) {
            return null;
        }

        return $value->getTimestamp();
    }

    public function isValidValue($value)
    {
        if (is_object($value)) {
            return true;
        }

        return is_int($value);
    }

    public function getDescription()
    {
        return 'A sequence of characters or encoded information identifying when a certain event occurred in seconds';
    }

}
