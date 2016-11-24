<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 7:46 PM
*/

namespace Youshido\Tests\Library\Field;


use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Field\InputField;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class ArgumentsAwareConfigTraitTest extends \PHPUnit_Framework_TestCase
{

    public function testArguments()
    {
        $argsData = [
            'id' => new IntType()
        ];
        $config   = new FieldConfig([
            'name' => 'UserType',
            'type' => new IntType(),
            'args' => $argsData
        ]);

        $this->assertTrue($config->hasArguments());
        $this->assertEquals([
            'id' => new InputField(['name' => 'id', 'type' => new IntType()]),
        ], $config->getArguments());

        $config->addArgument('name', new StringType());
        $this->assertEquals([
            'id'   => new InputField(['name' => 'id', 'type' => new IntType()]),
            'name' => new InputField(['name' => 'name', 'type' => new StringType()])
        ], $config->getArguments());

        $config->removeArgument('id');
        $this->assertEquals([
            'name' => new InputField(['name' => 'name', 'type' => new StringType()])
        ], $config->getArguments());

        $config->addArguments([
            'id' => new InputField(['name' => 'id', 'type' => new IntType()])
        ]);
        $this->assertEquals([
            'name' => new InputField(['name' => 'name', 'type' => new StringType()]),
            'id'   => new InputField(['name' => 'id', 'type' => new IntType()]),
        ], $config->getArguments());

        $config->addArguments([
            new InputField(['name' => 'level', 'type' => new IntType()])
        ]);
        $this->assertEquals([
            'name'  => new InputField(['name' => 'name', 'type' => new StringType()]),
            'id'    => new InputField(['name' => 'id', 'type' => new IntType()]),
            'level' => new InputField(['name' => 'level', 'type' => new IntType()]),
        ], $config->getArguments());

    }

}
