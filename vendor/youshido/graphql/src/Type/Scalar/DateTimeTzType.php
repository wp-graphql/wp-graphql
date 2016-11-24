<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/27/15 1:22 AM
*/

namespace Youshido\GraphQL\Type\Scalar;

class DateTimeTzType extends AbstractScalarType
{
    public function getName()
    {
        return 'DateTimeTz';
    }

    /**
     * @param $value \DateTime
     * @return null|string
     */
    public function serialize($value)
    {
        if ($value === null) {
            return null;
        }

        return $value->format('r');
    }

    public function parseValue($value)
    {
        return is_object($value) ? $this->serialize($value) : $value;
    }

    public function isValidValue($value)
    {
        if (is_object($value)) {
            return true;
        }

        $d = \DateTime::createFromFormat('D, d M Y H:i:s O', $value);

        return $d && $d->format('r') == $value;
    }

    public function getDescription()
    {
        return 'Representation of date and time in "r" format';
    }

}
