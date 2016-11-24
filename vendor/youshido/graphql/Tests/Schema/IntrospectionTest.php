<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/15/16 7:52 AM
*/

namespace Youshido\Tests\Schema;


use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Field\Field;
use Youshido\GraphQL\Type\Enum\EnumType;
use Youshido\GraphQL\Type\InterfaceType\InterfaceType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\GraphQL\Type\Union\UnionType;
use Youshido\Tests\DataProvider\TestEmptySchema;
use Youshido\Tests\DataProvider\TestSchema;

class IntrospectionTest extends \PHPUnit_Framework_TestCase
{
    private $introspectionQuery = <<<TEXT
query IntrospectionQuery {
                __schema {
                    queryType { name }
                    mutationType { name }
                    types {
                        ...FullType
                    }
                    directives {
                        name
                        description
                        args {
                            ...InputValue
                        }
                        onOperation
                        onFragment
                        onField
                    }
                }
            }

            fragment FullType on __Type {
                kind
                name
                description
                fields {
                    name
                    description
                    args {
                        ...InputValue
                    }
                    type {
                        ...TypeRef
                    }
                    isDeprecated
                    deprecationReason
                }
                inputFields {
                    ...InputValue
                }
                interfaces {
                    ...TypeRef
                }
                enumValues {
                    name
                    description
                    isDeprecated
                    deprecationReason
                }
                possibleTypes {
                    ...TypeRef
                }
            }

            fragment InputValue on __InputValue {
                name
                description
                type { ...TypeRef }
                defaultValue
            }

            fragment TypeRef on __Type {
                kind
                name
                ofType {
                    kind
                    name
                    ofType {
                        kind
                        name
                        ofType {
                            kind
                            name
                        }
                    }
                }
            }
TEXT;


    public function testIntrospectionDirectiveRequest()
    {
        $processor = new Processor(new TestSchema());

        $processor->processPayload($this->introspectionQuery, []);

        $this->assertTrue(is_array($processor->getResponseData()));
    }

    /**
     * @param $query
     * @param $expectedResponse
     *
     * @dataProvider predefinedSchemaProvider
     */
    public function testPredefinedQueries($query, $expectedResponse)
    {
        $schema = new TestEmptySchema();
        $schema->addQueryField(new Field([
            'name'              => 'latest',
            'type'              => new ObjectType([
                'name'   => 'LatestType',
                'fields' => [
                    'id'   => ['type' => TypeMap::TYPE_INT],
                    'name' => ['type' => TypeMap::TYPE_STRING]
                ],
            ]),
            'args'              => [
                'id' => ['type' => TypeMap::TYPE_INT, 'default' => 'test'],
                'id2' => ['type' => TypeMap::TYPE_INT]
            ],
            'description'       => 'latest description',
            'deprecationReason' => 'for test',
            'isDeprecated'      => true,
            'resolve'           => function () {
                return [
                    'id'   => 1,
                    'name' => 'Alex'
                ];
            }
        ]));

        $processor = new Processor($schema);

        $processor->processPayload($query);
        $responseData = $processor->getResponseData();

        $this->assertEquals($expectedResponse, $responseData);
    }

    public function predefinedSchemaProvider()
    {
        return [
            [
                '{ __type { name } }',
                [
                    'data'   => ['__type' => null],
                    'errors' => [['message' => 'Require "name" arguments to query "__type"']]
                ]
            ],
            [
                '{ __type (name: "__Type") { name } }',
                [
                    'data' => [
                        '__type' => ['name' => '__Type']
                    ]
                ]
            ],
            [
                '{ __type (name: "InvalidName") { name } }',
                [
                    'data' => [
                        '__type' => null
                    ]
                ]
            ],
            [
                '{
                    __schema {
                        types {
                            name,
                            fields (includeDeprecated: true) {
                                name
                                args {
                                    defaultValue
                                }
                            }
                        }
                    }
                }',
                [
                    'data' => [
                        '__schema' => [
                            'types' => [
                                ['name' => 'TestSchemaQuery', 'fields' => [['name' => 'latest', 'args' => [['defaultValue' => '"test"'], ['defaultValue' => null]]]]],
                                ['name' => 'Int', 'fields' => null],
                                ['name' => 'LatestType', 'fields' => [['name' => 'id', 'args' => []], ['name' => 'name', 'args' => []]]],
                                ['name' => 'String', 'fields' => null],
                                ['name' => '__Schema', 'fields' => [['name' => 'queryType', 'args' => []], ['name' => 'mutationType', 'args' => []], ['name' => 'subscriptionType', 'args' => []], ['name' => 'types', 'args' => []], ['name' => 'directives', 'args' => []]]],
                                ['name' => '__Type', 'fields' => [['name' => 'name', 'args' => []], ['name' => 'kind', 'args' => []], ['name' => 'description', 'args' => []], ['name' => 'ofType', 'args' => []], ['name' => 'inputFields', 'args' => []], ['name' => 'enumValues', 'args' => [['defaultValue' => 'false']]], ['name' => 'fields', 'args' => [['defaultValue' => 'false']]], ['name' => 'interfaces', 'args' => []], ['name' => 'possibleTypes', 'args' => []]]],
                                ['name' => '__InputValue', 'fields' => [['name' => 'name', 'args' => []], ['name' => 'description', 'args' => []], ['name' => 'type', 'args' => []], ['name' => 'defaultValue', 'args' => []],]],
                                ['name' => 'Boolean', 'fields' => null],
                                ['name' => '__EnumValue', 'fields' => [['name' => 'name', 'args' => []], ['name' => 'description', 'args' => []], ['name' => 'deprecationReason', 'args' => []], ['name' => 'isDeprecated', 'args' => []],]],
                                ['name' => '__Field', 'fields' => [['name' => 'name', 'args' => []], ['name' => 'description', 'args' => []], ['name' => 'isDeprecated', 'args' => []], ['name' => 'deprecationReason', 'args' => []], ['name' => 'type', 'args' => []], ['name' => 'args', 'args' => []]]],
                                ['name' => '__Subscription', 'fields' => [['name' => 'name', 'args' => []]]],
                                ['name' => '__Directive', 'fields' => [['name' => 'name', 'args' => []], ['name' => 'description', 'args' => []], ['name' => 'args', 'args' => []], ['name' => 'onOperation', 'args' => []], ['name' => 'onFragment', 'args' => []], ['name' => 'onField', 'args' => []]]],
                            ]
                        ]
                    ]
                ]
            ],
            [
                '{
                  test : __schema {
                    queryType {
                      kind,
                      name,
                      fields (includeDeprecated: true) {
                        name,
                        isDeprecated,
                        deprecationReason,
                        description,
                        type {
                          name
                        }
                      }
                    }
                  }
                }',
                ['data' => [
                    'test' => [
                        'queryType' => [
                            'name'   => 'TestSchemaQuery',
                            'kind'   => 'OBJECT',
                            'fields' => [
                                ['name' => 'latest', 'isDeprecated' => true, 'deprecationReason' => 'for test', 'description' => 'latest description', 'type' => ['name' => 'LatestType']]
                            ]
                        ]
                    ]
                ]]
            ],
            [
                '{
                  __schema {
                    queryType {
                      kind,
                      name,
                      description,
                      interfaces {
                        name
                      },
                      possibleTypes {
                        name
                      },
                      inputFields {
                        name
                      },
                      ofType{
                        name
                      }
                    }
                  }
                }',
                ['data' => [
                    '__schema' => [
                        'queryType' => [
                            'kind'          => 'OBJECT',
                            'name'          => 'TestSchemaQuery',
                            'description'   => null,
                            'interfaces'    => [],
                            'possibleTypes' => null,
                            'inputFields'   => null,
                            'ofType'        => null
                        ]
                    ]
                ]]
            ]
        ];
    }

    public function testCombinedFields()
    {
        $schema = new TestEmptySchema();

        $interface = new InterfaceType([
            'name'        => 'TestInterface',
            'fields'      => [
                'id'   => ['type' => new IntType()],
                'name' => ['type' => new IntType()],
            ],
            'resolveType' => function ($type) {

            }
        ]);

        $object1 = new ObjectType([
            'name'       => 'Test1',
            'fields'     => [
                'id'       => ['type' => new IntType()],
                'name'     => ['type' => new IntType()],
                'lastName' => ['type' => new IntType()],
            ],
            'interfaces' => [$interface]
        ]);

        $object2 = new ObjectType([
            'name'       => 'Test2',
            'fields'     => [
                'id'        => ['type' => new IntType()],
                'name'      => ['type' => new IntType()],
                'thirdName' => ['type' => new IntType()],
            ],
            'interfaces' => [$interface]
        ]);

        $unionType = new UnionType([
            'name'        => 'UnionType',
            'types'       => [$object1, $object2],
            'resolveType' => function () {

            }
        ]);

        $schema->addQueryField(new Field([
            'name'    => 'union',
            'type'    => $unionType,
            'args'    => [
                'id' => ['type' => TypeMap::TYPE_INT]
            ],
            'resolve' => function () {
                return [
                    'id'   => 1,
                    'name' => 'Alex'
                ];
            }
        ]));

        $schema->addMutationField(new Field([
            'name'    => 'mutation',
            'type'    => $unionType,
            'args'    => [
                'type' => new EnumType([
                    'name'   => 'MutationType',
                    'values' => [
                        [
                            'name'  => 'Type1',
                            'value' => 'type_1'
                        ],
                        [
                            'name'  => 'Type2',
                            'value' => 'type_2'
                        ]
                    ]
                ])
            ],
            'resolve' => function () {
                return null;
            }
        ]));

        $processor = new Processor($schema);

        $processor->processPayload($this->introspectionQuery);
        $responseData = $processor->getResponseData();

        /** strange that this test got broken after I fixed the field resolve behavior */
        $this->assertArrayNotHasKey('errors', $responseData);
    }

}
