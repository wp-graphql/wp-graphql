<?php

namespace Youshido\Tests\Schema;

use Youshido\GraphQL\Config\Object\InterfaceTypeConfig;
use Youshido\GraphQL\Config\Object\ObjectTypeConfig;
use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\InterfaceType\InterfaceType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IdType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;


class UserType extends AbstractObjectType
{
    public function build($config)
    {
        $config->addFields([
            'id'           => new IdType(),
            'fullName'     => new StringType(),
            'reservations' => new ListType(new ReservationInterface())
        ]);
    }
}

class CourtReservation extends AbstractObjectType
{

    public function build($config)
    {
        $config->addFields([
            'id'      => new IdType(),
            'players' => new ListType(new ObjectType([
                'name'   => 'Player',
                'fields' => [
                    'id'   => new IdType(),
                    'user' => new UserType()
                ]
            ]))
        ]);
    }

    public function getInterfaces()
    {
        return [new ReservationInterface()];
    }

}

class ClassReservation extends AbstractObjectType
{
    public function build($config)
    {
        $config->addFields([
            'id'   => new IdType(),
            'user' => new UserType()
        ]);
    }

    public function getInterfaces()
    {
        return [new ReservationInterface()];
    }
}

class ReservationInterface extends AbstractInterfaceType
{
    public function resolveType($object)
    {
        return strpos($object['id'], 'cl') === false ? new CourtReservation() : new ClassReservation();
    }

    public function build($config)
    {
        $config->addFields([
            'id' => new IdType()
        ]);
    }

}

class FragmentsTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider queries
     *
     * @param $query
     * @param $expected
     * @param $variables
     */
    public function testVariables($query, $expected, $variables)
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'user' => [
                        'type'    => new UserType(),
                        'resolve' => function ($args) {
                            return [
                                'id'           => 'user-id-1',
                                'fullName'     => 'Alex',
                                'reservations' => [
                                    [
                                        'id'   => 'cl-1',
                                        'user' => [
                                            'id'       => 'user-id-2',
                                            'fullName' => 'User class1'
                                        ],
                                    ],
                                    [
                                        'id'      => 'court-1',
                                        'players' => [
                                            [
                                                'id'       => 'player-id-1',
                                                'user' => [
                                                    'id' => 'user-id-3',
                                                    'fullName' => 'User court1'
                                                ]
                                            ]
                                        ]
                                    ],
                                ]
                            ];
                        },
                    ],
                ]
            ])
        ]);

        $processor = new Processor($schema);
        $processor->processPayload($query, $variables);
        $result = $processor->getResponseData();

        $this->assertEquals($expected, $result);
    }

    public function queries()
    {
        return [
            [
                'query {
                    user {
                        ...fUser
                        reservations {
                            ...fReservation
                        }
                    }
                }
                fragment fReservation on ReservationInterface {
                    id
                    ... on CourtReservation {
                        players {
                            id
                            user {
                                ...fUser
                            }
                        }
                    }
                    ... on ClassReservation {
                        user {
                            ...fUser
                        }
                    }
                }
                fragment fUser on User {
                    id
                    fullName
                }',
                [
                    'data' => [
                        'user' => [
                            'id'           => 'user-id-1',
                            'fullName'     => 'Alex',
                            'reservations' => [
                                [
                                    'id'   => 'cl-1',
                                    'user' => [
                                        'id'       => 'user-id-2',
                                        'fullName' => 'User class1'
                                    ]
                                ],
                                [
                                    'id'      => 'court-1',
                                    'players' => [
                                        [
                                            'id'       => 'player-id-1',
                                            'user' => [
                                                'id' => 'user-id-3',
                                                'fullName' => 'User court1'
                                            ]
                                        ]
                                    ]
                                ],
                            ]
                        ]
                    ],
                ],
                [
                ]
            ],
        ];
    }

}