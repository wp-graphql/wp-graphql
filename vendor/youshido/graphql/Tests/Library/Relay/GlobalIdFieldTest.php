<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 10:51 PM 5/18/16
 */

namespace Youshido\Tests\Library\Relay;


use Youshido\GraphQL\Relay\Field\GlobalIdField;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\IdType;

class GlobalIdFieldTest extends \PHPUnit_Framework_TestCase
{

    public function testSimpleMethods()
    {
        $typeName = 'user';
        $field    = new GlobalIdField($typeName);
        $this->assertEquals('id', $field->getName());
        $this->assertEquals('The ID of an object', $field->getDescription());
        $this->assertEquals(new NonNullType(new IdType()), $field->getType());
    }
}
