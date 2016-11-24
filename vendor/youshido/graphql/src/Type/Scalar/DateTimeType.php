<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/27/15 1:22 AM
*/

namespace Youshido\GraphQL\Type\Scalar;

class DateTimeType extends AbstractScalarType
{

    private $format;

    public function __construct($format = 'Y-m-d H:i:s')
    {
        $this->format = $format;
    }

    public function getName()
    {
        return 'DateTime';
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

        return $value->format($this->format);
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

        $d = \DateTime::createFromFormat($this->format, $value);

        return $d && $d->format($this->format) == $value;
    }

    public function getDescription()
    {
        return 'Representation of date and time in "Y-m-d H:i:s" format';
    }

}
