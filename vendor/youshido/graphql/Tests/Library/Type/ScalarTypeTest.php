<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 11/27/15 1:11 AM
*/

namespace Youshido\Tests\Library\Type;

use Youshido\GraphQL\Type\Scalar\AbstractScalarType;
use Youshido\GraphQL\Type\Scalar\DateTimeType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\GraphQL\Type\TypeFactory;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\TypeService;
use Youshido\Tests\DataProvider\TestScalarDataProvider;

class ScalarTypeTest extends \PHPUnit_Framework_TestCase
{

    public function testScalarPrimitives()
    {
        foreach (TypeFactory::getScalarTypesNames() as $typeName) {
            $scalarType     = TypeFactory::getScalarType($typeName);
            $testDataMethod = 'get' . $typeName . 'TestData';

            $this->assertNotEmpty($scalarType->getDescription());
            $this->assertEquals($scalarType->getKind(), TypeMap::KIND_SCALAR);
            $this->assertEquals($scalarType->isCompositeType(), false);
            $this->assertEquals(TypeService::isAbstractType($scalarType), false);
            $this->assertEquals($scalarType->getType(), $scalarType);
            $this->assertEquals($scalarType->getType(), $scalarType->getNamedType());
            $this->assertNull($scalarType->getConfig());

            foreach (call_user_func(['Youshido\Tests\DataProvider\TestScalarDataProvider', $testDataMethod]) as list($data, $serialized, $isValid)) {

                $this->assertSerialization($scalarType, $data, $serialized);
                $this->assertParse($scalarType, $data, $serialized);

                if ($isValid) {
                    $this->assertTrue($scalarType->isValidValue($data), $typeName . ' validation for :' . serialize($data));
                } else {
                    $this->assertFalse($scalarType->isValidValue($data), $typeName . ' validation for :' . serialize($data));
                }
            }
        }
        try {
            TypeFactory::getScalarType('invalid type');
        } catch (\Exception $e) {
            $this->assertEquals('Configuration problem with type invalid type', $e->getMessage());
        }
        $this->assertEquals('String', (string)new StringType());

    }

    public function testDateTimeType()
    {
        $dateType = new DateTimeType('Y/m/d H:i:s');
        $this->assertEquals('2016/05/31 12:00:00', $dateType->serialize(new \DateTime('2016-05-31 12:00pm')));
    }

    private function assertSerialization(AbstractScalarType $object, $input, $expected)
    {
        $this->assertEquals($expected, $object->serialize($input), $object->getName() . ' serialize for: ' . serialize($input));
    }

    private function assertParse(AbstractScalarType $object, $input, $expected)
    {
        $this->assertEquals($expected, $object->parseValue($input), $object->getName() . ' serialize for: ' . serialize($input));
    }

}
