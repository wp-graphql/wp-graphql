<?php
/**
 * @author: Ivo Meißner
 * Date: 22.02.16
 * Time: 18:35
 */
namespace GraphQLRelay\Tests\Connection;

use GraphQL\GraphQL;
use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLRelay\Connection\ArrayConnection;
use GraphQLRelay\Connection\Connection;

class SeparateConnectionTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected $allUsers;

    /**
     * @var ObjectType
     */
    protected $userType;

    /**
     * @var ObjectType
     */
    protected $friendEdge;

    /**
     * @var ObjectType
     */
    protected $friendConnection;
    
    /**
     * @var ObjectType
     */
    protected $userEdge;

    /**
     * @var ObjectType
     */
    protected $userConnection;

    /**
     * @var ObjectType
     */
    protected $queryType;

    /**
     * @var Schema
     */
    protected $schema;

    public function setup()
    {
        $this->allUsers = [
            [ 'name' => 'Dan', 'friends' => [1, 2, 3, 4] ],
            [ 'name' => 'Nick', 'friends' => [0, 2, 3, 4] ],
            [ 'name' => 'Lee', 'friends' => [0, 1, 3, 4] ],
            [ 'name' => 'Joe', 'friends' => [0, 1, 2, 4] ],
            [ 'name' => 'Tim', 'friends' => [0, 1, 2, 3] ],
        ];

        $this->userType = new ObjectType([
            'name' => 'User',
            'fields' => function(){
                return [
                    'name' => [
                        'type' => Type::string()
                    ],
                    'friends' => [
                        'type' => $this->friendConnection,
                        'args' => Connection::connectionArgs(),
                        'resolve' => function ($user, $args) {
                            return ArrayConnection::connectionFromArray($user['friends'], $args);
                        }
                    ],
                    'friendsForward' => [
                        'type' => $this->userConnection,
                        'args' => Connection::forwardConnectionArgs(),
                        'resolve' => function ($user, $args) {
                            return ArrayConnection::connectionFromArray($user['friends'], $args);
                        }
                    ],
                    'friendsBackward' => [
                        'type' => $this->userConnection,
                        'args' => Connection::backwardConnectionArgs(),
                        'resolve' => function ($user, $args) {
                            return ArrayConnection::connectionFromArray($user['friends'], $args);
                        }
                    ]
                ];
            }
        ]);

        $this->friendEdge = Connection::createEdgeType([
            'name' => 'Friend',
            'nodeType' => $this->userType,
            'resolveNode' => function ($edge) {
                return $this->allUsers[$edge['node']];
            },
            'edgeFields' => function() {
                return [
                    'friendshipTime' => [
                        'type' => Type::string(),
                        'resolve' => function() { return 'Yesterday'; }
                    ]
                ];
            }
        ]);

        $this->friendConnection = Connection::createConnectionType([
            'name' => 'Friend',
            'nodeType' => $this->userType,
            'edgeType' => $this->friendEdge,
            'connectionFields' => function() {
                return [
                    'totalCount' => [
                        'type' => Type::int(),
                        'resolve' => function() {
                            return count($this->allUsers) -1;
                        }
                    ]
                ];
            }
        ]);

        $this->userEdge = Connection::createEdgeType([
            'nodeType' => $this->userType,
            'resolveNode' => function ($edge) {
                return $this->allUsers[$edge['node']];
            }
        ]);

        $this->userConnection = Connection::createConnectionType([
            'nodeType' => $this->userType,
            'edgeType' => $this->userEdge
        ]);

        $this->queryType = new ObjectType([
            'name' => 'Query',
            'fields' => function() {
                return [
                    'user' => [
                        'type' => $this->userType,
                        'resolve' => function() {
                            return $this->allUsers[0];
                        }
                    ]
                ];
            }
        ]);

        $this->schema = new Schema([
            'query' => $this->queryType
        ]);
    }

    public function testIncludesConnectionAndEdgeFields()
    {
        $query = 'query FriendsQuery {
            user {
              friends(first: 2) {
                totalCount
                edges {
                  friendshipTime
                  node {
                    name
                  }
                }
              }
            }
          }';

        $expected = [
            'user' => [
                'friends' => [
                    'totalCount' => 4,
                    'edges' => [
                        [
                            'friendshipTime' => 'Yesterday',
                            'node' => [
                                'name' => 'Nick'
                            ]
                        ],
                        [
                            'friendshipTime' => 'Yesterday',
                            'node' => [
                                'name' => 'Lee'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testWorksWithForwardConnectionArgs()
    {
        $query = 'query FriendsQuery {
            user {
              friendsForward(first: 2) {
                edges {
                  node {
                    name
                  }
                }
              }
            }
          }';
        $expected = [
            'user' => [
                'friendsForward' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => 'Nick'
                            ]
                        ],
                        [
                            'node' => [
                                'name' => 'Lee'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    public function testWorksWithBackwardConnectionArgs()
    {
        $query = 'query FriendsQuery {
            user {
              friendsBackward(last: 2) {
                edges {
                  node {
                    name
                  }
                }
              }
            }
          }';

        $expected = [
            'user' => [
                'friendsBackward' => [
                    'edges' => [
                        [
                            'node' => [
                                'name' => 'Joe'
                            ]
                        ],
                        [
                            'node' => [
                                'name' => 'Tim'
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $this->assertValidQuery($query, $expected);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    protected function assertValidQuery($query, $expected)
    {
        $result = GraphQL::execute($this->schema, $query);
        $this->assertEquals(['data' => $expected], $result);
    }
}
