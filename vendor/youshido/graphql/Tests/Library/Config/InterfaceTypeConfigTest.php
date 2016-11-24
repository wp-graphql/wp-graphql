<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 4:17 PM
*/

namespace Youshido\Tests\Library\Config;


use Youshido\GraphQL\Config\Object\InterfaceTypeConfig;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\GraphQL\Validator\ConfigValidator\ConfigValidator;
use Youshido\Tests\DataProvider\TestInterfaceType;

class InterfaceTypeConfigTest extends \PHPUnit_Framework_TestCase
{

    public function testCreation()
    {
        $config = new InterfaceTypeConfig(['name' => 'Test'], null, false);
        $this->assertEquals($config->getName(), 'Test', 'Normal creation');
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testConfigNoFields()
    {
        ConfigValidator::getInstance()->assertValidConfig(
            new InterfaceTypeConfig(['name' => 'Test', 'resolveType' => function () { }], null, true)
        );
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testConfigNoResolve()
    {
        ConfigValidator::getInstance()->assertValidConfig(
            new InterfaceTypeConfig(['name' => 'Test', 'fields' => ['id' => new IntType()]], null, true)
        );
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testConfigInvalidResolve()
    {
        $config = new InterfaceTypeConfig(['name' => 'Test', 'fields' => ['id' => new IntType()]], null, false);
        $config->resolveType(['invalid object']);
    }

    public function testInterfaces()
    {
        $interfaceConfig = new InterfaceTypeConfig([
            'name'        => 'Test',
            'fields'      => ['id' => new IntType()],
            'resolveType' => function ($object) {
                return $object->getType();
            }
        ], null, true);
        $object          = new ObjectType(['name' => 'User', 'fields' => ['name' => new StringType()]]);

        $this->assertEquals($interfaceConfig->getName(), 'Test');
        $this->assertEquals($interfaceConfig->resolveType($object), $object->getType());

        $testInterface                = new TestInterfaceType();
        $interfaceConfigWithNoResolve = new InterfaceTypeConfig([
            'name'   => 'Test',
            'fields' => ['id' => new IntType()]
        ], $testInterface, false);
        $this->assertEquals($interfaceConfigWithNoResolve->resolveType($object), $object);
    }


}
