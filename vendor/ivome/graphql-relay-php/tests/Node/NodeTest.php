<?php
/**
 * @author: Ivo Meißner
 * Date: 22.02.16
 * Time: 13:14
 */

namespace GraphQLRelay\Tests\Node;


use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\Type;
use GraphQLRelay\Node\Node;


class NodeTest extends \PHPUnit_Framework_TestCase {
    /**
     * Node definition, so that it is only created once
     *
     * @var array
     */
    protected static $nodeDefinition;

    /**
     * @var ObjectType
     */
    protected static $userType;

    /**
     * @var ObjectType
     */
    protected static $photoType;

    public function testGetsCorrectIDForUsers() {
        $query = '{
            node(id: "1") {
              id
            }
          }';

        $expected = [
            'node' => [
                'id' => 1
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testGetsCorrectIDForPhotos() {
        $query = '{
            node(id: "4") {
            id
            }
          }';

        $expected = [
            'node' => [
                'id' => 4
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testGetsCorrectNameForUsers() {
        $query = '{
            node(id: "1") {
              id
              ... on User {
                name
              }
            }
          }';

        $expected = [
            'node' => [
                'id' => '1',
                'name' => 'John Doe'
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testGetsCorrectWidthForPhotos() {
        $query = '{
            node(id: "4") {
              id
              ... on Photo {
                width
              }
            }
          }';

        $expected = [
            'node' => [
                'id' => '4',
                'width' => 400
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testGetsCorrectTypeNameForUsers() {
        $query = '{
            node(id: "1") {
              id
              __typename
            }
          }';

        $expected = [
            'node' => [
                'id' => '1',
                '__typename' => 'User'
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testCorrectWidthForPhotos() {
        $query = '{
            node(id: "4") {
              id
              __typename
            }
          }';

        $expected = [
            'node' => [
                'id' => '4',
                '__typename' => 'Photo'
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testIgnoresPhotoFragmentsOnUser() {
        $query = '{
        node(id: "1") {
          id
          ... on Photo {
            width
          }
        }
      }';
        $expected = [
            'node' => [
                'id' => '1'
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testReturnsNullForBadIDs() {
        $query = '{
            node(id: "5") {
              id
            }
          }';

        $expected = [
            'node' => null
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testHasCorrectNodeInterface() {
        $query = '{
            __type(name: "Node") {
              name
              kind
              fields {
                name
                type {
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
                'name' => 'Node',
                'kind' => 'INTERFACE',
                'fields' => [
                    [
                        'name' => 'id',
                        'type' => [
                            'kind' => 'NON_NULL',
                            'ofType' => [
                                'name' => 'ID',
                                'kind' => 'SCALAR'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testHasCorrectNodeRootField() {
        $query = '{
            __schema {
              queryType {
                fields {
                  name
                  type {
                    name
                    kind
                  }
                  args {
                    name
                    type {
                      kind
                      ofType {
                        name
                        kind
                      }
                    }
                  }
                }
              }
            }
          }';

        $expected = [
            '__schema' => [
                'queryType' => [
                    'fields' => [
                        [
                            'name' => 'node',
                            'type' => [
                                'name' => 'Node',
                                'kind' => 'INTERFACE'
                            ],
                            'args' => [
                                [
                                    'name' => 'id',
                                    'type' => [
                                        'kind' => 'NON_NULL',
                                        'ofType' => [
                                            'name' => 'ID',
                                            'kind' => 'SCALAR'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    /**
     * Returns test schema
     *
     * @return Schema
     */
    protected function getSchema() {
        return new Schema([
            'query' => $this->getQueryType(),

            // We have to pass the types here manually because graphql-php cannot
            // recognize types that are only available through interfaces
            // https://github.com/webonyx/graphql-php/issues/38
            'types' => [
                self::$userType,
                self::$photoType
            ]
        ]);
    }

    /**
     * Returns test query type
     *
     * @return ObjectType
     */
    protected function getQueryType() {
        $nodeField = $this->getNodeDefinitions();
        return new ObjectType([
            'name' => 'Query',
            'fields' => [
                'node' => $nodeField['nodeField']
            ]
        ]);
    }

    /**
     * Returns node definitions
     *
     * @return array
     */
    protected function getNodeDefinitions() {
        if (!self::$nodeDefinition){
            self::$nodeDefinition = Node::nodeDefinitions(
                function($id, $context, ResolveInfo $info) {
                    $userData = $this->getUserData();
                    if (array_key_exists($id, $userData)){
                        return $userData[$id];
                    } else {
                        $photoData = $this->getPhotoData();
                        if (array_key_exists($id, $photoData)){
                            return $photoData[$id];
                        }
                    }
                },
                function($obj) {
                    if (array_key_exists($obj['id'], $this->getUserData())){
                        return self::$userType;
                    } else {
                        return self::$photoType;
                    }
                }
            );

            self::$userType = new ObjectType([
                'name' => 'User',
                'fields' => [
                    'id' => [
                        'type' => Type::nonNull(Type::id()),
                    ],
                    'name' => [
                        'type' => Type::string()
                    ]
                ],
                'interfaces' => [self::$nodeDefinition['nodeInterface']]
            ]);

            self::$photoType = new ObjectType([
                'name' => 'Photo',
                'fields' => [
                    'id' => [
                        'type' => Type::nonNull(Type::id())
                    ],
                    'width' => [
                        'type' => Type::int()
                    ]
                ],
                'interfaces' => [self::$nodeDefinition['nodeInterface']]
            ]);
        }
        return self::$nodeDefinition;
    }

    /**
     * Returns photo data
     *
     * @return array
     */
    protected function getPhotoData() {
        return  [
            '3' => [
                'id' => 3,
                'width' => 300
            ],
            '4' => [
                'id' => 4,
                'width' => 400
            ]
        ];
    }

    /**
     * Returns user data
     *
     * @return array
     */
    protected function getUserData() {
        return [
            '1' => [
                'id' => 1,
                'name' => 'John Doe'
            ],
            '2' => [
                'id' => 2,
                'name' => 'Jane Smith'
            ]
        ];
    }
    
    /**
     * Helper function to test a query and the expected response.
     */
    private function assertValidQuery($query, $expected)
    {
        $result = GraphQL::execute($this->getSchema(), $query);

        $this->assertEquals(['data' => $expected], $result);
    }

}