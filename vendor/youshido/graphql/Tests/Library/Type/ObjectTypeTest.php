<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/11/16 9:43 PM
*/

namespace Youshido\Tests\Library\Type;


use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Validator\ConfigValidator\ConfigValidator;
use Youshido\Tests\DataProvider\TestMutationObjectType;
use Youshido\Tests\DataProvider\TestObjectType;

class ObjectTypeTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testCreatingInvalidObject()
    {
        new ObjectType([]);
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidNameParam()
    {
        $type = new ObjectType([
            'name' => null
        ]);
        ConfigValidator::getInstance()->assertValidConfig($type->getConfig());
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidFieldsParam()
    {
        $type = new ObjectType([
            'name'   => 'SomeName',
            'fields' => []
        ]);
        ConfigValidator::getInstance()->assertValidConfig($type->getConfig());
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ResolveException
     */
    public function testSerialize()
    {
        $object = new ObjectType([
            'name'   => 'SomeName',
            'fields' => [
                'name' => new StringType()
            ]
        ]);
        $object->serialize([]);
    }


    public function testNormalCreatingParam()
    {
        $objectType = new ObjectType([
            'name'        => 'Post',
            'fields'      => [
                'id' => new IntType()
            ],
            'description' => 'Post type description'
        ]);
        $this->assertEquals($objectType->getKind(), TypeMap::KIND_OBJECT);
        $this->assertEquals($objectType->getName(), 'Post');
        $this->assertEquals($objectType->getType(), $objectType);
        $this->assertEquals($objectType->getType()->getName(), 'Post');
        $this->assertEquals($objectType->getNamedType(), $objectType);

        $this->assertEmpty($objectType->getInterfaces());
        $this->assertTrue($objectType->isValidValue($objectType));
        $this->assertTrue($objectType->isValidValue(null));

        $this->assertEquals('Post type description', $objectType->getDescription());
    }

    public function testFieldsTrait()
    {
        $idField = new Field(['name' => 'id', 'type' => new IntType()]);
        $nameField = new Field(['name' => 'name', 'type' => new StringType()]);

        $objectType = new ObjectType([
            'name'        => 'Post',
            'fields'      => [
                $idField
            ],
            'description' => 'Post type description'
        ]);
        $this->assertTrue($objectType->hasFields());
        $this->assertEquals([
            'id' => $idField
        ], $objectType->getFields());

        $objectType->addField($nameField);
        $this->assertEquals([
            'id'   => $idField,
            'name' => $nameField,
        ], $objectType->getFields());
    }

    public function testExtendedClass()
    {
        $objectType = new TestObjectType();
        $this->assertEquals($objectType->getName(), 'TestObject');
        $this->assertEquals($objectType->getType(), $objectType, 'test type of extended object');

        $this->assertNull($objectType->getDescription());
    }

    public function testMutationObjectClass()
    {
        $mutation = new TestMutationObjectType();
        $this->assertEquals(new StringType(), $mutation->getType());
    }

}
