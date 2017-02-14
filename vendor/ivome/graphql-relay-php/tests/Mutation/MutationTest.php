<?php
/**
 * @author: Ivo Meißner
 * Date: 23.02.16
 * Time: 12:27
 */
namespace GraphQLRelay\Tests\Mutation;


use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLRelay\Connection\Connection;
use GraphQLRelay\Mutation\Mutation;

class MutationTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ObjectType
     */
    protected $simpleMutation;

    /**
     * @var ObjectType
     */
    protected $simpleMutationWithThunkFields;

    /**
     * @var ObjectType
     */
    protected $mutation;

    /**
     * @var ObjectType
     */
    protected $edgeMutation;

    /**
     * @var Schema
     */
    protected $schema;

    public function setup()
    {
        $this->simpleMutation = Mutation::mutationWithClientMutationId([
            'name' => 'SimpleMutation',
            'inputFields' => [],
            'outputFields' => [
                'result' => [
                    'type' => Type::int()
                ]
            ],
            'mutateAndGetPayload' => function () {
                return ['result' => 1];
            }
        ]);

        $this->simpleMutationWithThunkFields = Mutation::mutationWithClientMutationId([
            'name' => 'SimpleMutationWithThunkFields',
            'inputFields' => function() {
                return [
                    'inputData' => [
                        'type' => Type::int()
                    ]
                ];
            },
            'outputFields' => function() {
                return [
                    'result' => [
                        'type' => Type::int()
                    ]
                ];
            },
            'mutateAndGetPayload' => function($inputData) {
                return [
                    'result' => $inputData['inputData']
                ];
            }
        ]);

        $userType = new ObjectType([
           'name' => 'User',
            'fields' => [
                'name' => [
                    'type' => Type::string()
                ]
            ]
        ]);

        $this->edgeMutation = Mutation::mutationWithClientMutationId([
            'name' => 'EdgeMutation',
            'inputFields' => [],
            'outputFields' => [
                'result' => [
                    'type' => Connection::createEdgeType(['nodeType' => $userType ])
                ]
            ],
            'mutateAndGetPayload' => function () {
                return ['result' => ['node' => ['name' => 'Robert'], 'cursor' => 'SWxvdmVHcmFwaFFM']];
            }
        ]);

        $this->mutation = new ObjectType([
            'name' => 'Mutation',
            'fields' => [
                'simpleMutation' => $this->simpleMutation,
                'simpleMutationWithThunkFields' => $this->simpleMutationWithThunkFields,
                'edgeMutation' => $this->edgeMutation
            ]
        ]);

        $this->schema = new Schema([
            'mutation' => $this->mutation,
            'query' => $this->mutation
        ]);
    }

    public function testRequiresAnArgument() {
        $query = 'mutation M {
            simpleMutation {
              result
            }
          }';

        $result = GraphQL::execute($this->schema, $query);

        $this->assertEquals(count($result['errors']), 1);
        $this->assertEquals($result['errors'][0]['message'], 'Field "simpleMutation" argument "input" of type "SimpleMutationInput!" is required but not provided.');
    }

    public function testReturnsTheSameClientMutationID()
    {
        $query = 'mutation M {
            simpleMutation(input: {clientMutationId: "abc"}) {
              result
              clientMutationId
            }
          }';

        $expected = [
            'simpleMutation' => [
                'result' => 1,
                'clientMutationId' => 'abc'
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }


    public function testSupportsEdgeAsOutputField()
    {
        $query = 'mutation M {
            edgeMutation(input: {clientMutationId: "abc"}) {
              result {
                  node {
                      name
                  }
                  cursor
              }
              clientMutationId
            }
          }';

        $expected = [
            'edgeMutation' => [
                'result' => [
                    'node' => ['name' => 'Robert'],
                    'cursor' => 'SWxvdmVHcmFwaFFM'
                ],
                'clientMutationId' => 'abc'
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testIntrospection()
    {
        $query = '{
            __type(name: "SimpleMutationInput") {
              name
              kind
              inputFields {
                name
                type {
                  name
                  kind
                  ofType {
                    name
                    kind
                  }
                }
              }
            }
          }';

        $expected = [
            '__type' => [
                'name' => 'SimpleMutationInput',
                'kind' => 'INPUT_OBJECT',
                'inputFields' => [
                    [
                        'name' => 'clientMutationId',
                        'type' => [
                            'name' => null,
                            'kind' => 'NON_NULL',
                            'ofType' => [
                                'name' => 'String',
                                'kind' => 'SCALAR'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testContainsCorrectPayload() {
        $query = '{
            __type(name: "SimpleMutationPayload") {
              name
              kind
              fields {
                name
                type {
                  name
                  kind
                  ofType {
                    name
                    kind
                  }
                }
              }
            }
          }';

        $expected = [
            '__type' => [
                'name' => 'SimpleMutationPayload',
                'kind' => 'OBJECT',
                'fields' => [
                    [
                        'name' => 'result',
                        'type' => [
                            'name' => 'Int',
                            'kind' => 'SCALAR',
                            'ofType' => null
                        ]
                    ],
                    [
                        'name' => 'clientMutationId',
                        'type' => [
                            'name' => null,
                            'kind' => 'NON_NULL',
                            'ofType' => [
                                'name' => 'String',
                                'kind' => 'SCALAR'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testContainsCorrectField()
    {
        $query = '{
            __schema {
              mutationType {
                fields {
                  name
                  args {
                    name
                    type {
                      name
                      kind
                      ofType {
                        name
                        kind
                      }
                    }
                  }
                  type {
                    name
                    kind
                  }
                }
              }
            }
          }';

        $expected = [
            '__schema' => [
                'mutationType' => [
                    'fields' => [
                        [
                            'name' => 'simpleMutation',
                            'args' => [
                                [
                                    'name' => 'input',
                                    'type' => [
                                        'name' => null,
                                        'kind' => 'NON_NULL',
                                        'ofType' => [
                                            'name' => 'SimpleMutationInput',
                                            'kind' => 'INPUT_OBJECT'
                                        ]
                                    ],
                                ]
                            ],
                            'type' => [
                                'name' => 'SimpleMutationPayload',
                                'kind' => 'OBJECT',
                            ]
                        ],
                        [
                            'name' => 'simpleMutationWithThunkFields',
                            'args' => [
                                [
                                    'name' => 'input',
                                    'type' => [
                                        'name' => null,
                                        'kind' => 'NON_NULL',
                                        'ofType' => [
                                            'name' => 'SimpleMutationWithThunkFieldsInput',
                                            'kind' => 'INPUT_OBJECT'
                                        ]
                                    ],
                                ]
                            ],
                            'type' => [
                                'name' => 'SimpleMutationWithThunkFieldsPayload',
                                'kind' => 'OBJECT',
                            ]
                        ],
                        [
                            'name' => 'edgeMutation',
                            'args' => [
                                [
                                    'name' => 'input',
                                    'type' => [
                                        'name' => null,
                                        'kind' => 'NON_NULL',
                                        'ofType' => [
                                            'name' => 'EdgeMutationInput',
                                            'kind' => 'INPUT_OBJECT'
                                        ]
                                    ],
                                ]
                            ],
                            'type' => [
                                'name' => 'EdgeMutationPayload',
                                'kind' => 'OBJECT',
                            ]
                        ],
                        /*
                         * Promises not implemented right now
                        [
                            'name' => 'simplePromiseMutation',
                            'args' => [
                                [
                                    'name' => 'input',
                                    'type' => [
                                        'name' => null,
                                        'kind' => 'NON_NULL',
                                        'ofType' => [
                                            'name' => 'SimplePromiseMutationInput',
                                            'kind' => 'INPUT_OBJECT'
                                        ]
                                    ],
                                ]
                            ],
                            'type' => [
                                'name' => 'SimplePromiseMutationPayload',
                                'kind' => 'OBJECT',
                            ]
                        ]*/
                    ]
                ]
            ]
        ];

        $result = GraphQL::execute($this->schema, $query);

        $this->assertValidQuery($query, $expected);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    protected function assertValidQuery($query, $expected)
    {
        $this->assertEquals(['data' => $expected], GraphQL::execute($this->schema, $query));
    }
}
