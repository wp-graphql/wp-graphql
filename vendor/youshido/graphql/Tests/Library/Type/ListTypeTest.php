<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/15/16 2:46 PM
*/

namespace Youshido\Tests\Library\Type;


use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\Tests\DataProvider\TestListType;


class ListTypeTest extends \PHPUnit_Framework_TestCase
{

    public function testInline()
    {
        $listType = new ListType(new StringType());
        $this->assertEquals(new StringType(), $listType->getNamedType());
        $this->assertEquals(new StringType(), $listType->getTypeOf());
        $this->assertTrue($listType->isCompositeType());
        $this->assertTrue($listType->isValidValue(['Test', 'Value']));
        $this->assertFalse($listType->isValidValue('invalid value'));
    }

    public function testStandaloneClass()
    {
        $listType = new TestListType();
        $this->assertEquals(new StringType(), $listType->getNamedType());
    }

    public function testListOfInputsWithArguments()
    {

    }

}
