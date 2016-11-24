<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 7:08 PM
*/

namespace Youshido\Tests\Library\Config;


use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Type\Scalar\StringType;

class FieldConfigTest extends \PHPUnit_Framework_TestCase
{

    public function testInvalidParams()
    {
        $fieldConfig = new FieldConfig([
            'name'    => 'FirstName',
            'type'    => new StringType(),
            'resolve' => function ($value, $args = [], $type = null) {
                return 'John';
            }
        ]);

        $this->assertEquals('FirstName', $fieldConfig->getName());
        $this->assertEquals(new StringType(), $fieldConfig->getType());

        $resolveFunction = $fieldConfig->getResolveFunction();
        $this->assertEquals('John', $resolveFunction([]));
    }

}
