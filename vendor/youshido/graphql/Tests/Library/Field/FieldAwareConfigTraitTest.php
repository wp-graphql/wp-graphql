<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 7:46 PM
*/

namespace Youshido\Tests\Library\Field;


use Youshido\GraphQL\Config\Object\ObjectTypeConfig;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class FieldAwareConfigTraitTest extends \PHPUnit_Framework_TestCase
{

    public function testAddField()
    {
        $fieldsData = [
            'id' => [
                'type' => new IntType()
            ]
        ];
        $config     = new ObjectTypeConfig([
            'name'   => 'UserType',
            'fields' => $fieldsData
        ]);

        $this->assertTrue($config->hasFields());
        $idField = new Field(['name' => 'id', 'type' => new IntType()]);
        $idField->getName();
        $nameField = new Field(['name' => 'name', 'type' => new StringType()]);

        $this->assertEquals([
            'id' => $idField,
        ], $config->getFields());

        $config->addField($nameField);
        $this->assertEquals([
            'id'   => $idField,
            'name' => $nameField
        ], $config->getFields());

        $config->removeField('id');
        $this->assertEquals([
            'name' => $nameField
        ], $config->getFields());

        $config->addFields([
            'id' => $idField
        ]);
        $this->assertEquals([
            'name' => $nameField,
            'id'   => $idField,
        ], $config->getFields());

        $levelField = new Field(['name' => 'level', 'type' => new IntType()]);
        $config->addFields([
            $levelField
        ]);
        $this->assertEquals([
            'name'  => $nameField,
            'id'    => $idField,
            'level' => $levelField,
        ], $config->getFields());

    }

}
