<?php

namespace Youshido\Tests\Parser;

use Youshido\GraphQL\Parser\Ast\ArgumentValue\Variable;

class VariableTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test if variable value equals expected value
     *
     * @dataProvider variableProvider
     */
    public function testGetValue($actual, $expected)
    {
        $var = new Variable('foo', 'bar');
        $var->setValue($actual);
        $this->assertEquals($var->getValue(), $expected);
    }

    /**
     * @expectedException \LogicException
     * @expectedExceptionMessage Value is not set for variable "foo"
     */
    public function testGetNullValueException()
    {
        $var = new Variable('foo', 'bar');
        $var->getValue();
    }

    /**
     * @return array Array of <mixed: value to set, mixed: expected value>
     */
    public static function variableProvider()
    {
        return [
            [
                0,
                0
            ]
        ];
    }
}