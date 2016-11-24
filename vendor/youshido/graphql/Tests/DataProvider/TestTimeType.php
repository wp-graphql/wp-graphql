<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 8/14/16 12:26 PM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Type\Scalar\AbstractScalarType;

class TestTimeType extends AbstractScalarType
{

    public function getName()
    {
        return 'TestTime';
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

        return $value instanceof \DateTime ? $value->format('H:i:s') : $value;
    }

    public function isValidValue($value)
    {
        if (is_object($value)) {
            return true;
        }

        $d = \DateTime::createFromFormat('H:i:s', $value);

        return $d && $d->format('H:i:s') == $value;
    }

    public function getDescription()
    {
        return 'Representation time in "H:i:s" format';
    }

}