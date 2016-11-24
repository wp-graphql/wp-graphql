<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 10:11 PM
*/

namespace Youshido\Tests\Schema;


use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\Tests\DataProvider\TestEmptySchema;
use Youshido\Tests\DataProvider\TestObjectType;
use Youshido\Tests\DataProvider\TestSchema;

class SchemaTest extends \PHPUnit_Framework_TestCase
{

    public function testInlineSchema()
    {
        $queryType = new ObjectType([
            'name'   => 'RootQueryType',
            'fields' => [
                'currentTime' => [
                    'type'    => new StringType(),
                    'resolve' => function ($value, $args, $type) {
                        return 'May 5, 9:00am';
                    },
                    'args'    => [
                        'gmt' => [
                            'type'    => new IntType(),
                            'default' => -5
                        ],
                    ],
                ]
            ]
        ]);
//        $this->assertEquals('May 5, 9:00am', $queryType->getField('currentTime')->resolve([], [],));
    }

    public function testStandaloneEmptySchema()
    {
        $schema = new TestEmptySchema();
        $this->assertFalse($schema->getQueryType()->hasFields());
    }

    public function testStandaloneSchema()
    {
        $schema = new TestSchema();
        $this->assertTrue($schema->getQueryType()->hasFields());
        $this->assertTrue($schema->getMutationType()->hasFields());

        $this->assertEquals(1, count($schema->getMutationType()->getFields()));

        $schema->addMutationField('changeUser', ['type' => new TestObjectType(), 'resolve' => function () { }]);
        $this->assertEquals(2, count($schema->getMutationType()->getFields()));

    }

    public function testSchemaWithoutClosuresSerializable()
    {
        $schema = new TestEmptySchema();
        $schema->getQueryType()->addField('randomInt', [
            'type'    => new NonNullType(new IntType()),
            'resolve' => 'rand',
        ]);

        $serialized = serialize($schema);
        /** @var Schema $unserialized */
        $unserialized = unserialize($serialized);

        $this->assertTrue($unserialized->getQueryType()->hasFields());
        $this->assertFalse($unserialized->getMutationType()->hasFields());
        $this->assertEquals(1, count($unserialized->getQueryType()->getFields()));
    }

    public function testCustomTypes()
    {
        $authorType = null;

        $userInterface = new ObjectType([
            'name'        => 'UserInterface',
            'fields'      => [
                'name' => new StringType(),
            ],
            'resolveType' => function () use ($authorType) {
                return $authorType;
            }
        ]);

        $authorType = new ObjectType([
            'name'       => 'Author',
            'fields'     => [
                'name' => new StringType(),
            ],
            'interfaces' => [$userInterface]
        ]);

        $schema    = new Schema([
            'query' => new ObjectType([
                'name'   => 'QueryType',
                'fields' => [
                    'user' => [
                        'type'    => $userInterface,
                        'resolve' => function () {
                            return [
                                'name' => 'Alex'
                            ];
                        }
                    ]
                ]
            ])
        ]);
        $schema->getTypesList()->addType($authorType);
        $processor = new Processor($schema);
        $processor->processPayload('{ user { name } }');
        $this->assertEquals(['data' => ['user' => ['name' => 'Alex']]], $processor->getResponseData());

        $processor->processPayload('{
                    __schema {
                        types {
                            name
                        }
                    }
                }');
        $data = $processor->getResponseData();
        $this->assertArraySubset([11 => ['name' => 'Author']], $data['data']['__schema']['types']);

        $processor->processPayload('{ user { name { } } }');
        $result = $processor->getResponseData();

        $this->assertEquals(['errors' => [ ['message' => 'Unexpected token "RBRACE" at (1:19)']]], $result);
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ user { name { invalidSelection } } }');
        $result = $processor->getResponseData();

        $this->assertEquals(['data' => ['user' => null], 'errors' => [ ['message' => 'You can\'t specify fields for scalar type "String"']]], $result);
    }

}
