<?php
/**
 * Date: 13.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\tests\Library\Type;


use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\Union\UnionType;
use Youshido\GraphQL\Validator\ConfigValidator\ConfigValidator;
use Youshido\Tests\DataProvider\TestObjectType;
use Youshido\Tests\DataProvider\TestUnionType;

class UnionTypeTest extends \PHPUnit_Framework_TestCase
{

    public function testInlineCreation()
    {
        $object = new ObjectType([
            'name' => 'TestObject',
            'fields' => ['id' => ['type' => new IntType()]]
        ]);

        $type = new UnionType([
            'name'        => 'Car',
            'description' => 'Union collect cars types',
            'types'       => [
                new TestObjectType(),
                $object
            ],
            'resolveType' => function ($type) {
                return $type;
            }
        ]);

        $this->assertEquals('Car', $type->getName());
        $this->assertEquals('Union collect cars types', $type->getDescription());
        $this->assertEquals([new TestObjectType(), $object], $type->getTypes());
        $this->assertEquals('test', $type->resolveType('test'));
        $this->assertEquals(TypeMap::KIND_UNION, $type->getKind());
        $this->assertEquals($type, $type->getNamedType());
        $this->assertTrue($type->isValidValue(true));
    }

    public function testObjectCreation()
    {
        $type = new TestUnionType();

        $this->assertEquals('TestUnion', $type->getName());
        $this->assertEquals('Union collect cars types', $type->getDescription());
        $this->assertEquals([new TestObjectType()], $type->getTypes());
        $this->assertEquals('test', $type->resolveType('test'));
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidTypesWithScalar()
    {
        $type = new UnionType([
            'name'        => 'Car',
            'description' => 'Union collect cars types',
            'types'       => [
                'test', new IntType()
            ],
            'resolveType' => function ($type) {
                return $type;
            }
        ]);
        ConfigValidator::getInstance()->assertValidConfig($type->getConfig());
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidTypes()
    {
        $type = new UnionType([
            'name'        => 'Car',
            'description' => 'Union collect cars types',
            'types'       => [
                new IntType()
            ],
            'resolveType' => function ($type) {
                return $type;
            }
        ]);
        ConfigValidator::getInstance()->assertValidConfig($type->getConfig());
    }
}
