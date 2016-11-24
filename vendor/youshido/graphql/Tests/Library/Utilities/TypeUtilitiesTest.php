<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/11/16 10:25 PM
*/

namespace Youshido\Tests\Library\Utilities;

use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\TypeService;
use Youshido\Tests\DataProvider\TestInterfaceType;
use Youshido\Tests\DataProvider\TestObjectType;
use Youshido\Tests\Library\Type\ObjectTypeTest;

class TypeUtilitiesTest extends \PHPUnit_Framework_TestCase
{

    public function testTypeService()
    {
        $this->assertTrue(TypeService::isScalarType(TypeMap::TYPE_STRING));
        $this->assertFalse(TypeService::isScalarType('gibberish'));
        $this->assertFalse(TypeService::isScalarType(new TestObjectType()));

        $stringType = new StringType();

        $this->assertFalse(TypeService::isInterface($stringType));
        $this->assertEquals(TypeService::resolveNamedType($stringType), $stringType);
        $this->assertNull(TypeService::resolveNamedType(null));
        $this->assertEquals(TypeService::resolveNamedType(123), $stringType);
    }

    /**
     * @expectedException \Exception
     */
    public function testNamedTypeResolverException()
    {
        TypeService::resolveNamedType(['name' => 'test']);
    }

    public function testIsInputType()
    {
        $testType = new ObjectType(['name' => 'test', 'fields' => ['name' => new StringType()]]);
        $this->assertTrue(TypeService::isInputType(new StringType()));
        $this->assertTrue(TypeService::isInputType(TypeMap::TYPE_STRING));
        $this->assertFalse(TypeService::isInputType('invalid type'));
        $this->assertFalse(TypeService::isInputType($testType));
    }

    public function testIsAbstractType()
    {
        $this->assertTrue(TypeService::isAbstractType(new TestInterfaceType()));
        $this->assertFalse(TypeService::isAbstractType(new StringType()));
        $this->assertFalse(TypeService::isAbstractType('invalid type'));
    }

    public function testGetPropertyValue() {
        $arrayData = (new TestObjectType())->getData();

        $this->assertEquals('John', TypeService::getPropertyValue($arrayData, 'name'));
        $this->assertEquals('John', TypeService::getPropertyValue((object) $arrayData, 'name'));
    }
}
