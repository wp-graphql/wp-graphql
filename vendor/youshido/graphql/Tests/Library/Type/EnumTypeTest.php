<?php
/**
 * Date: 12.05.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\Tests\Library\Type;


use Youshido\GraphQL\Type\Enum\EnumType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Validator\ConfigValidator\ConfigValidator;
use Youshido\Tests\DataProvider\TestEnumType;

class EnumTypeTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidInlineCreation()
    {
        new EnumType([]);
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidEmptyParams()
    {
        $enumField = new EnumType([
            'values' => []
        ]);
        ConfigValidator::getInstance()->assertValidConfig($enumField->getConfig());

    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidValueParams()
    {
        $enumField = new EnumType([
            'values' => [
                'test'  => 'asd',
                'value' => 'asdasd'
            ]
        ]);
        ConfigValidator::getInstance()->assertValidConfig($enumField->getConfig());
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testExistingNameParams()
    {
        $enumField = new EnumType([
            'values' => [
                [
                    'test'  => 'asd',
                    'value' => 'asdasd'
                ]
            ]
        ]);
        ConfigValidator::getInstance()->assertValidConfig($enumField->getConfig());
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testInvalidNameParams()
    {
        $enumField = new EnumType([
            'values' => [
                [
                    'name'  => false,
                    'value' => 'asdasd'
                ]
            ]
        ]);
        ConfigValidator::getInstance()->assertValidConfig($enumField->getConfig());
    }

    /**
     * @expectedException Youshido\GraphQL\Validator\Exception\ConfigurationException
     */
    public function testWithoutValueParams()
    {
        $enumField = new EnumType([
            'values' => [
                [
                    'name' => 'TEST_ENUM',
                ]
            ]
        ]);
        ConfigValidator::getInstance()->assertValidConfig($enumField->getConfig());
    }

    public function testNormalCreatingParams()
    {
        $valuesData = [
            [
                'name'  => 'ENABLE',
                'value' => true
            ],
            [
                'name'  => 'DISABLE',
                'value' => 'disable'
            ]
        ];
        $enumType   = new EnumType([
            'name'   => 'BoolEnum',
            'values' => $valuesData
        ]);

        $this->assertEquals($enumType->getKind(), TypeMap::KIND_ENUM);
        $this->assertEquals($enumType->getName(), 'BoolEnum');
        $this->assertEquals($enumType->getType(), $enumType);
        $this->assertEquals($enumType->getNamedType(), $enumType);

        $this->assertFalse($enumType->isValidValue($enumType));
        $this->assertFalse($enumType->isValidValue(null));

        $this->assertTrue($enumType->isValidValue(true));
        $this->assertTrue($enumType->isValidValue('disable'));

        $this->assertNull($enumType->serialize('invalid value'));
        $this->assertNull($enumType->parseValue('invalid literal'));
        $this->assertTrue($enumType->parseValue('ENABLE'));

        $this->assertEquals($valuesData, $enumType->getValues());
    }

    public function testExtendedObject()
    {
        $testEnumType = new TestEnumType();
        $this->assertEquals('TestEnum', $testEnumType->getName());
    }

}
