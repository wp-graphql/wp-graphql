<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 11:02 PM 5/13/16
 */

namespace Youshido\Tests\Schema;


use Youshido\GraphQL\Execution\Container\Container;
use Youshido\GraphQL\Execution\Context\ExecutionContext;
use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Execution\Visitor\MaxComplexityQueryVisitor;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\Enum\EnumType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IdType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\GraphQL\Type\Union\UnionType;
use Youshido\Tests\DataProvider\TestEmptySchema;
use Youshido\Tests\DataProvider\TestEnumType;
use Youshido\Tests\DataProvider\TestInterfaceType;
use Youshido\Tests\DataProvider\TestObjectType;
use Youshido\Tests\DataProvider\TestSchema;

class ProcessorTest extends \PHPUnit_Framework_TestCase
{

    private $_counter = 0;

    public function testInit()
    {
        $processor = new Processor(new TestEmptySchema());
        $this->assertEquals([['message' => 'Schema has to have fields']], $processor->getExecutionContext()->getErrorsArray());
    }

    public function testEmptyQueries()
    {
        $processor = new Processor(new TestSchema());
        $processor->processPayload('');
        $this->assertEquals(['errors' => [
            ['message' => 'Must provide an operation.']
        ]], $processor->getResponseData());

        $processor->getExecutionContext()->clearErrors();
        $processor->processPayload('{ me { name } }');
        $this->assertEquals(['data' => [
            'me' => ['name' => 'John']
        ]], $processor->getResponseData());

    }

    public function testNestedVariables()
    {
        $processor    = new Processor(new TestSchema());
        $noArgsQuery  = '{ me { echo(value:"foo") } }';
        $expectedData = ['data' => ['me' => ['echo' => 'foo']]];
        $processor->processPayload($noArgsQuery, ['value' => 'foo']);
        $this->assertEquals($expectedData, $processor->getResponseData());

        $parameterizedFieldQuery =
            'query nestedFieldQuery($value:String!){
          me {
            echo(value:$value)
          }
        }';
        $processor->processPayload($parameterizedFieldQuery, ['value' => 'foo']);
        $response = $processor->getResponseData();
        $this->assertEquals($expectedData, $response);

        $parameterizedQueryQuery =
            'query nestedQueryQuery($value:Int){
          me {
            location(noop:$value) {
              address
            }
          }
        }';
        $processor->processPayload($parameterizedQueryQuery, ['value' => 1]);
        $this->assertArrayNotHasKey('errors', $processor->getResponseData());
    }

    public function testListNullResponse()
    {
        $processor = new Processor(new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'list' => [
                        'type'    => new ListType(new StringType()),
                        'resolve' => function () {
                            return null;
                        }
                    ]
                ]
            ])
        ]));
        $data      = $processor->processPayload(' { list }')->getResponseData();
        $this->assertEquals(['data' => ['list' => null]], $data);
    }


    public function testSubscriptionNullResponse()
    {
        $processor = new Processor(new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'list' => [
                        'type'    => new ListType(new StringType()),
                        'resolve' => function () {
                            return null;
                        }
                    ]
                ]
            ])
        ]));
        $data      = $processor->processPayload(' { __schema { subscriptionType { name } } }')->getResponseData();
        $this->assertEquals(['data' => ['__schema' => ['subscriptionType' => null]]], $data);
    }

    public function testSchemaOperations()
    {
        $schema    = new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'me'                => [
                        'type'    => new ObjectType([
                            'name'   => 'User',
                            'fields' => [
                                'firstName' => [
                                    'type'    => new StringType(),
                                    'args'    => [
                                        'shorten' => new BooleanType()
                                    ],
                                    'resolve' => function ($value, $args) {
                                        return empty($args['shorten']) ? $value['firstName'] : $value['firstName'];
                                    }
                                ],
                                'id_alias'  => [
                                    'type'    => new IdType(),
                                    'resolve' => function ($value) {
                                        return $value['id'];
                                    }
                                ],
                                'lastName'  => new StringType(),
                                'code'      => new StringType(),
                            ]
                        ]),
                        'resolve' => function ($value, $args) {
                            $data = ['id' => '123', 'firstName' => 'John', 'code' => '007'];
                            if (!empty($args['upper'])) {
                                foreach ($data as $key => $value) {
                                    $data[$key] = strtoupper($value);
                                }
                            }

                            return $data;
                        },
                        'args'    => [
                            'upper' => [
                                'type'    => new BooleanType(),
                                'default' => false
                            ]
                        ]
                    ],
                    'randomUser'        => [
                        'type'    => new TestObjectType(),
                        'resolve' => function () {
                            return ['invalidField' => 'John'];
                        }
                    ],
                    'invalidValueQuery' => [
                        'type'    => new TestObjectType(),
                        'resolve' => function () {
                            return 'stringValue';
                        }
                    ],
                    'labels'            => [
                        'type'    => new ListType(new StringType()),
                        'resolve' => function () {
                            return ['one', 'two'];
                        }
                    ]
                ],
            ])
        ]);
        $processor = new Processor($schema);

        $processor->processPayload('{ me { firstName } }');
        $this->assertEquals(['data' => ['me' => ['firstName' => 'John']]], $processor->getResponseData());

        $processor->processPayload('{ me { id_alias } }');
        $this->assertEquals(['data' => ['me' => ['id_alias' => '123']]], $processor->getResponseData());

        $processor->processPayload('{ me { firstName, lastName } }');
        $this->assertEquals(['data' => ['me' => ['firstName' => 'John', 'lastName' => null]]], $processor->getResponseData());

        $processor->processPayload('{ me { code } }');
        $this->assertEquals(['data' => ['me' => ['code' => 7]]], $processor->getResponseData());

        $processor->processPayload('{ me(upper:true) { firstName } }');
        $this->assertEquals(['data' => ['me' => ['firstName' => 'JOHN']]], $processor->getResponseData());

        $processor->processPayload('{ labels }');
        $this->assertEquals(['data' => ['labels' => ['one', 'two']]], $processor->getResponseData());

        $schema->getMutationType()
            ->addField(new Field([
                'name'    => 'increaseCounter',
                'type'    => new IntType(),
                'resolve' => function ($value, $args, ResolveInfo $info) {
                    return $this->_counter += $args['amount'];
                },
                'args'    => [
                    'amount' => [
                        'type'    => new IntType(),
                        'default' => 1
                    ]
                ]
            ]))->addField(new Field([
                'name'    => 'invalidResolveTypeMutation',
                'type'    => new NonNullType(new IntType()),
                'resolve' => function () {
                    return null;
                }
            ]))->addField(new Field([
                'name'    => 'interfacedMutation',
                'type'    => new TestInterfaceType(),
                'resolve' => function () {
                    return ['name' => 'John'];
                }
            ]));
        $processor->processPayload('mutation { increaseCounter }');
        $this->assertEquals(['data' => ['increaseCounter' => 1]], $processor->getResponseData());

        $processor->processPayload('mutation { invalidMutation }');
        $this->assertEquals(['errors' => [['message' => 'Field "invalidMutation" not found in type "RootSchemaMutation"']]], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('mutation { increaseCounter(noArg: 2) }');
        $this->assertEquals([
            'data'   => ['increaseCounter' => null],
            'errors' => [['message' => 'Unknown argument "noArg" on field "increaseCounter"']]
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('mutation { increaseCounter(amount: 2) { invalidProp } }');
        $this->assertEquals(['errors' => [['message' => 'You can\'t specify fields for scalar type "Int"']], 'data' => ['increaseCounter' => null]], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('mutation { increaseCounter(amount: 2) }');
        $this->assertEquals(['data' => ['increaseCounter' => 3]], $processor->getResponseData());

        $processor->processPayload('{ invalidQuery }');
        $this->assertEquals(['errors' => [['message' => 'Field "invalidQuery" not found in type "RootQuery"']]], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ invalidValueQuery { id } }');
        $this->assertEquals(['errors' => [['message' => 'Not valid resolved type for field "invalidValueQuery"']], 'data' => ['invalidValueQuery' => null]], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ me { firstName(shorten: true), middle }}');
        $this->assertEquals([
            'data'   => ['me' => null],
            'errors' => [['message' => 'Field "middle" not found in type "User"']],
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ randomUser { region }}');
        $this->assertEquals([
            'data'   => ['randomUser' => null],
            'errors' => [['message' => 'You have to specify fields for "region"']]
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('mutation { invalidResolveTypeMutation }');
        $this->assertEquals([
            'data'   => ['invalidResolveTypeMutation' => null],
            'errors' => [['message' => 'Cannot return null for non-nullable field "invalidResolveTypeMutation"']],
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('mutation { user:interfacedMutation { name }  }');
        $this->assertEquals(['data' => ['user' => ['name' => 'John']]], $processor->getResponseData());
    }

    public function testEnumType()
    {
        $processor = new Processor(new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'test' => [
                        'args'    => [
                            'argument1' => new NonNullType(new EnumType([
                                'name'   => 'TestEnumType',
                                'values' => [
                                    [
                                        'name'  => 'VALUE1',
                                        'value' => 'val1'
                                    ],
                                    [
                                        'name'  => 'VALUE2',
                                        'value' => 'val2'
                                    ]
                                ]
                            ]))
                        ],
                        'type'    => new StringType(),
                        'resolve' => function ($value, $args) {
                            return $args['argument1'];
                        }
                    ]
                ]
            ])
        ]));

        $processor->processPayload('{ test }');
        $response = $processor->getResponseData();
        $this->assertEquals([
            'data'   => ['test' => null],
            'errors' => [['message' => 'Require "argument1" arguments to query "test"']]
        ], $response);
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ alias: test() }');
        $response = $processor->getResponseData();
        $this->assertEquals([
            'data'   => ['alias' => null],
            'errors' => [['message' => 'Require "argument1" arguments to query "test"']]
        ], $response);
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ alias: test(argument1: VALUE4) }');
        $response = $processor->getResponseData();
        $this->assertEquals([
            'data'   => ['alias' => null],
            'errors' => [['message' => 'Not valid type for argument "argument1" in query "test"']]
        ], $response);
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ alias: test(argument1: VALUE1) }');
        $response = $processor->getResponseData();
        $this->assertEquals(['data' => ['alias' => 'val1']], $response);
    }

    public function testListEnumsSchemaOperations()
    {
        $processor = new Processor(new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'listQuery'                 => [
                        'type'    => new ListType(new TestEnumType()),
                        'resolve' => function () {
                            return 'invalid list';
                        }
                    ],
                    'listEnumQuery'             => [
                        'type'    => new ListType(new TestEnumType()),
                        'resolve' => function () {
                            return ['invalid enum'];
                        }
                    ],
                    'invalidEnumQuery'          => [
                        'type'    => new TestEnumType(),
                        'resolve' => function () {
                            return 'invalid enum';
                        }
                    ],
                    'enumQuery'                 => [
                        'type'    => new TestEnumType(),
                        'resolve' => function () {
                            return 1;
                        }
                    ],
                    'invalidNonNullQuery'       => [
                        'type'    => new NonNullType(new IntType()),
                        'resolve' => function () {
                            return null;
                        }
                    ],
                    'invalidNonNullInsideQuery' => [
                        'type'    => new NonNullType(new IntType()),
                        'resolve' => function () {
                            return 'hello';
                        }
                    ],
                    'objectQuery'               => [
                        'type'    => new TestObjectType(),
                        'resolve' => function () {
                            return ['name' => 'John'];
                        }
                    ],
                    'deepObjectQuery'           => [
                        'type'    => new ObjectType([
                            'name'   => 'deepObject',
                            'fields' => [
                                'object' => new TestObjectType(),
                                'enum'   => new TestEnumType(),
                            ],
                        ]),
                        'resolve' => function () {
                            return [
                                'object' => [
                                    'name' => 'John'
                                ],
                                'enum'   => 1
                            ];
                        },
                    ],
                ]
            ])
        ]));

        $processor->processPayload('{ listQuery }');
        $this->assertEquals([
            'data'   => ['listQuery' => null],
            'errors' => [['message' => 'Not valid resolved type for field "listQuery"']]
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ listEnumQuery }');
        $this->assertEquals([
            'data'   => ['listEnumQuery' => [null]],
            'errors' => [['message' => 'Not valid resolved type for field "listEnumQuery"']]
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ invalidEnumQuery }');
        $this->assertEquals([
            'data'   => ['invalidEnumQuery' => null],
            'errors' => [['message' => 'Not valid resolved type for field "invalidEnumQuery"']],
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ enumQuery }');
        $this->assertEquals(['data' => ['enumQuery' => 'FINISHED']], $processor->getResponseData());

        $processor->processPayload('{ invalidNonNullQuery }');
        $this->assertEquals([
            'data'   => ['invalidNonNullQuery' => null],
            'errors' => [['message' => 'Cannot return null for non-nullable field "invalidNonNullQuery"']],
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ invalidNonNullInsideQuery }');
        $this->assertEquals([
            'data'   => ['invalidNonNullInsideQuery' => null],
            'errors' => [['message' => 'Not valid resolved type for field "invalidNonNullInsideQuery"']],
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ test:deepObjectQuery { object { name } } }');
        $this->assertEquals(['data' => ['test' => ['object' => ['name' => 'John']]]], $processor->getResponseData());
    }

    public function testTypedFragment()
    {

        $object1 = new ObjectType([
            'name'   => 'Object1',
            'fields' => [
                'id' => ['type' => 'int', 'cost' => 13]
            ]
        ]);

        $object2 = new ObjectType([
            'name'   => 'Object2',
            'fields' => [
                'name' => ['type' => 'string']
            ]
        ]);

        $object3 = new ObjectType([
            'name'   => 'Object3',
            'fields' => [
                'name' => ['type' => 'string']
            ]
        ]);

        $union        = new UnionType([
            'name'        => 'TestUnion',
            'types'       => [$object1, $object2],
            'resolveType' => function ($object) use ($object1, $object2) {
                if (isset($object['id'])) {
                    return $object1;
                }

                return $object2;
            }
        ]);
        $invalidUnion = new UnionType([
            'name'        => 'TestUnion',
            'types'       => [$object1, $object2],
            'resolveType' => function ($object) use ($object3) {
                return $object3;
            }
        ]);
        $processor    = new Processor(new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'union'        => [
                        'type'    => $union,
                        'args'    => [
                            'type' => ['type' => 'string']
                        ],
                        'cost'    => 10,
                        'resolve' => function ($value, $args) {
                            if ($args['type'] == 'object1') {
                                return [
                                    'id' => 43
                                ];
                            } else {
                                return [
                                    'name' => 'name resolved'
                                ];
                            }
                        }
                    ],
                    'invalidUnion' => [
                        'type'    => $invalidUnion,
                        'resolve' => function () {
                            return ['name' => 'name resolved'];
                        }
                    ],
                ]
            ])
        ]));
        $processor->processPayload('{ union(type: "object1") { ... on Object2 { id } } }');
        $this->assertEquals(['data' => ['union' => []]], $processor->getResponseData());

        $processor->processPayload('{ union(type: "object1") { ... on Object1 { name } } }');
        $this->assertEquals([
            'data'   => [
                'union' => null
            ],
            'errors' => [
                ['message' => 'Field "name" not found in type "Object1"']
            ]
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        $processor->processPayload('{ union(type: "object1") { ... on Object1 { id } } }');
        $this->assertEquals(['data' => ['union' => ['id' => 43]]], $processor->getResponseData());

        $processor->processPayload('{ union(type: "asd") { ... on Object2 { name } } }');
        $this->assertEquals(['data' => ['union' => ['name' => 'name resolved']]], $processor->getResponseData());

        $processor->processPayload('{ invalidUnion { ... on Object2 { name } } }');
        $this->assertEquals([
            'data'   => [
                'invalidUnion' => null
            ],
            'errors' => [
                ['message' => 'Type "Object3" not exist in types of "TestUnion"']
            ]
        ], $processor->getResponseData());

        $visitor = new MaxComplexityQueryVisitor(1000); // arbitrarily high cost
        $processor->processPayload('{ union(type: "object1") { ... on Object1 { id } } }', [], [$visitor]);
        $this->assertEquals(10 + 13, $visitor->getMemo());

        $visitor = new MaxComplexityQueryVisitor(1000); // arbitrarily high cost
        $processor->processPayload('{ union(type: "object1") { ... on Object1 { id }, ... on Object2 { name } } }', [], [$visitor]);
        $this->assertEquals(10 + 13 + 1, $visitor->getMemo());

        // planning phase currently has no knowledge of what types the union will resolve to, this will have the same score as above
        $visitor = new MaxComplexityQueryVisitor(1000); // arbitrarily high cost
        $processor->processPayload('{ union(type: "object2") { ... on Object1 { id }, ... on Object2 { name } } }', [], [$visitor]);
        $this->assertEquals(10 + 13 + 1, $visitor->getMemo());
    }

    public function testContainer()
    {
        $container = new Container();
        $container->set('user', ['name' => 'Alex']);

        $executionContext = new ExecutionContext(new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'currentUser' => [
                        'type'    => new StringType(),
                        'resolve' => function ($source, $args, ResolveInfo $info) {
                            return $info->getContainer()->get('user')['name'];
                        }
                    ]
                ]
            ])
        ]));
        $executionContext->setContainer($container);
        $this->assertNotNull($executionContext->getContainer());

        $processor = new Processor($executionContext->getSchema());
        $processor->getExecutionContext()->setContainer($container);

        $this->assertEquals(['data' => ['currentUser' => 'Alex']], $processor->processPayload('{ currentUser }')->getResponseData());
    }

    public function testComplexityReducer()
    {
        $schema    = new Schema(
            [
                'query' => new ObjectType(
                    [
                        'name'   => 'RootQuery',
                        'fields' => [
                            'me' => [
                                'type'    => new ObjectType(
                                    [
                                        'name'   => 'User',
                                        'fields' => [
                                            'firstName' => [
                                                'type'    => new StringType(),
                                                'args'    => [
                                                    'shorten' => new BooleanType()
                                                ],
                                                'resolve' => function ($value, $args) {
                                                    return empty($args['shorten']) ? $value['firstName'] : $value['firstName'];
                                                }
                                            ],
                                            'lastName'  => new StringType(),
                                            'code'      => new StringType(),
                                            'likes'     => [
                                                'type'    => new IntType(),
                                                'cost'    => 10,
                                                'resolve' => function () {
                                                    return 42;
                                                }
                                            ]
                                        ]
                                    ]
                                ),
                                'cost'    => function ($args, $context, $childCost) {
                                    $argsCost = isset($args['cost']) ? $args['cost'] : 1;

                                    return 1 + $argsCost * $childCost;
                                },
                                'resolve' => function ($value, $args) {
                                    $data = ['firstName' => 'John', 'code' => '007'];

                                    return $data;
                                },
                                'args'    => [
                                    'cost' => [
                                        'type'    => new IntType(),
                                        'default' => 1
                                    ]
                                ]
                            ]
                        ]
                    ]
                )
            ]
        );
        $processor = new Processor($schema);

        $processor->setMaxComplexity(10);

        $processor->processPayload('{ me { firstName, lastName } }');
        $this->assertArrayNotHasKey('error', $processor->getResponseData());

        $processor->processPayload('{ me { } }');
        $this->assertEquals(['errors' => [['message' => 'Unexpected token "RBRACE" at (1:10)']]], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();


        $processor->processPayload('{ me { firstName, likes } }');
        $this->assertEquals(['errors' => [['message' => 'query exceeded max allowed complexity of 10']]], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        // don't let complexity reducer affect query errors
        $processor->processPayload('{ me { badfield } }');
        $this->assertArraySubset(['errors' => [['message' => 'Field "badfield" not found in type "User"']]], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();

        foreach (range(1, 5) as $cost_multiplier) {
            $visitor = new MaxComplexityQueryVisitor(1000); // arbitrarily high cost
            $processor->processPayload("{ me (cost: $cost_multiplier) { firstName, lastName, code, likes } }", ['cost' => $cost_multiplier], [$visitor]);
            $expected = 1 + 13 * (1 + $cost_multiplier);
            $this->assertEquals($expected, $visitor->getMemo());
        }

        // TODO, variables not yet supported
        /*$query = 'query costQuery ($cost: Int) { me (cost: $cost) { firstName, lastName, code, likes } }';
        foreach (range(1,5) as $cost_multiplier) {
          $visitor = new \Youshido\GraphQL\Execution\Visitor\MaxComplexityQueryVisitor(1000); // arbitrarily high cost
          $processor->processPayload($query, ['cost' => $cost_multiplier], [$visitor]);
          $expected = 1 + 13 * (1 + $cost_multiplier);
          $this->assertEquals($expected, $visitor->getMemo());
        }*/
    }
}
