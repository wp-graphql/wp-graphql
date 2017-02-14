<?php
/**
 * @author: Ivo Meißner
 * Date: 29.02.16
 * Time: 10:17
 */

namespace GraphQLRelay\tests;


use GraphQL\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;
use GraphQLRelay\Relay;

class StarWarsSchema {
    protected static $shipConnection;
    protected static $factionType;
    protected static $shipType;
    protected static $nodeDefinition;
    protected static $shipMutation;

    /**
     * This is a basic end-to-end test, designed to demonstrate the various
     * capabilities of a Relay-compliant GraphQL server.
     *
     * It is recommended that readers of this test be familiar with
     * the end-to-end test in GraphQL.js first, as this test skips
     * over the basics covered there in favor of illustrating the
     * key aspects of the Relay spec that this test is designed to illustrate.
     *
     * We will create a GraphQL schema that describes the major
     * factions and ships in the original Star Wars trilogy.
     *
     * NOTE: This may contain spoilers for the original Star
     * Wars trilogy.
     */

    /**
     * Using our shorthand to describe type systems, the type system for our
     * example will be the followng:
     *
     * interface Node {
     *   id: ID!
     * }
     *
     * type Faction : Node {
     *   id: ID!
     *   name: String
     *   ships: ShipConnection
     * }
     *
     * type Ship : Node {
     *   id: ID!
     *   name: String
     * }
     *
     * type ShipConnection {
     *   edges: [ShipEdge]
     *   pageInfo: PageInfo!
     * }
     *
     * type ShipEdge {
     *   cursor: String!
     *   node: Ship
     * }
     *
     * type PageInfo {
     *   hasNextPage: Boolean!
     *   hasPreviousPage: Boolean!
     *   startCursor: String
     *   endCursor: String
     * }
     *
     * type Query {
     *   rebels: Faction
     *   empire: Faction
     *   node(id: ID!): Node
     * }
     *
     * input IntroduceShipInput {
     *   clientMutationId: string!
     *   shipName: string!
     *   factionId: ID!
     * }
     *
     * input IntroduceShipPayload {
     *   clientMutationId: string!
     *   ship: Ship
     *   faction: Faction
     * }
     *
     * type Mutation {
     *   introduceShip(input IntroduceShipInput!): IntroduceShipPayload
     * }
     */

    /**
     * We get the node interface and field from the relay library.
     *
     * The first method is the way we resolve an ID to its object. The second is the
     * way we resolve an object that implements node to its type.
     */
    protected static function getNodeDefinition()
    {
        if (self::$nodeDefinition === null){
            $nodeDefinition = Relay::nodeDefinitions(
                // The ID fetcher definition
                function ($globalId) {
                    $idComponents = Relay::fromGlobalId($globalId);
                    if ($idComponents['type'] === 'Faction'){
                        return StarWarsData::getFaction($idComponents['id']);
                    } else if ($idComponents['type'] === 'Ship'){
                        return StarWarsData::getShip($idComponents['id']);
                    } else {
                        return null;
                    }
                },
                // Type resolver
                function ($object) {
                    return isset($object['ships']) ? self::getFactionType() : self::getShipType();
                }
            );
            self::$nodeDefinition = $nodeDefinition;
        }

        return self::$nodeDefinition;
    }

    /**
     * We define our basic ship type.
     *
     * This implements the following type system shorthand:
     *   type Ship : Node {
     *     id: String!
     *     name: String
     *   }
     *
     * @return ObjectType
     */
    protected static function getShipType()
    {
        if (self::$shipType === null){
            $nodeDefinition = self::getNodeDefinition();

            $shipType = new ObjectType([
                'name' => 'Ship',
                'description' => 'A ship in the Star Wars saga',
                'fields' => function() {
                    return [
                        'id' => Relay::globalIdField(),
                        'name' => [
                            'type' => Type::string(),
                            'description' => 'The name of the ship.'
                        ]
                    ];
                },
                'interfaces' => [$nodeDefinition['nodeInterface']]
            ]);
            self::$shipType = $shipType;
        }
        return self::$shipType;
    }

    /**
     * We define our faction type, which implements the node interface.
     *
     * This implements the following type system shorthand:
     *   type Faction : Node {
     *     id: String!
     *     name: String
     *     ships: ShipConnection
     *   }
     *
     * @return ObjectType
     */
    protected static function getFactionType()
    {
        if (self::$factionType === null){
            $shipConnection = self::getShipConnection();
            $nodeDefinition = self::getNodeDefinition();

            $factionType = new ObjectType([
                'name' => 'Faction',
                'description' => 'A faction in the Star Wars saga',
                'fields' => function() use ($shipConnection) {
                    return [
                        'id' => Relay::globalIdField(),
                        'name' => [
                            'type' => Type::string(),
                            'description' => 'The name of the faction.'
                        ],
                        'ships' => [
                            'type' => $shipConnection['connectionType'],
                            'description' => 'The ships used by the faction.',
                            'args' => Relay::connectionArgs(),
                            'resolve' => function($faction, $args) {
                                // Map IDs from faction back to ships
                                $data = array_map(function($id) {
                                    return StarWarsData::getShip($id);
                                }, $faction['ships']);
                                return Relay::connectionFromArray($data, $args);
                            }
                        ]
                    ];
                },
                'interfaces' => [$nodeDefinition['nodeInterface']]
            ]);

            self::$factionType = $factionType;
        }

        return self::$factionType;
    }

    /**
     * We define a connection between a faction and its ships.
     *
     * connectionType implements the following type system shorthand:
     *   type ShipConnection {
     *     edges: [ShipEdge]
     *     pageInfo: PageInfo!
     *   }
     *
     * connectionType has an edges field - a list of edgeTypes that implement the
     * following type system shorthand:
     *   type ShipEdge {
     *     cursor: String!
     *     node: Ship
     *   }
     */
    protected static function getShipConnection()
    {
        if (self::$shipConnection === null){
            $shipType = self::getShipType();
            $shipConnection = Relay::connectionDefinitions([
                'nodeType' => $shipType
            ]);

            self::$shipConnection = $shipConnection;
        }

        return self::$shipConnection;
    }

    /**
     * This will return a GraphQLFieldConfig for our ship
     * mutation.
     *
     * It creates these two types implicitly:
     *   input IntroduceShipInput {
     *     clientMutationId: string!
     *     shipName: string!
     *     factionId: ID!
     *   }
     *
     *   input IntroduceShipPayload {
     *     clientMutationId: string!
     *     ship: Ship
     *     faction: Faction
     *   }
     */
    public static function getShipMutation()
    {
        if (self::$shipMutation === null){
            $shipType = self::getShipType();
            $factionType = self::getFactionType();

            $shipMutation = Relay::mutationWithClientMutationId([
                'name' => 'IntroduceShip',
                'inputFields' => [
                    'shipName' => [
                        'type' => Type::nonNull(Type::string())
                    ],
                    'factionId' => [
                        'type' => Type::nonNull(Type::id())
                    ]
                ],
                'outputFields' => [
                    'ship' => [
                        'type' => $shipType,
                        'resolve' => function ($payload) {
                            return StarWarsData::getShip($payload['shipId']);
                        }
                    ],
                    'faction' => [
                        'type' => $factionType,
                        'resolve' => function ($payload) {
                            return StarWarsData::getFaction($payload['factionId']);
                        }
                    ]
                ],
                'mutateAndGetPayload' => function ($input) {
                    $newShip = StarWarsData::createShip($input['shipName'], $input['factionId']);
                    return [
                        'shipId' => $newShip['id'],
                        'factionId' => $input['factionId']
                    ];
                }
            ]);
            self::$shipMutation = $shipMutation;
        }

        return self::$shipMutation;
    }

    /**
     * Returns the complete schema for StarWars tests
     *
     * @return Schema
     */
    public static function getSchema()
    {
        $factionType = self::getFactionType();
        $nodeDefinition = self::getNodeDefinition();
        $shipMutation = self::getShipMutation();

        /**
         * This is the type that will be the root of our query, and the
         * entry point into our schema.
         *
         * This implements the following type system shorthand:
         *   type Query {
         *     rebels: Faction
         *     empire: Faction
         *     node(id: String!): Node
         *   }
         */
        $queryType = new ObjectType([
            'name' => 'Query',
            'fields' => function () use ($factionType, $nodeDefinition) {
                return [
                    'rebels' => [
                        'type' => $factionType,
                        'resolve' => function (){
                            return StarWarsData::getRebels();
                        }
                    ],
                    'empire' => [
                        'type' => $factionType,
                        'resolve' => function () {
                            return StarWarsData::getEmpire();
                        }
                    ],
                    'node' => $nodeDefinition['nodeField']
                ];
            },
        ]);

        /**
         * This is the type that will be the root of our mutations, and the
         * entry point into performing writes in our schema.
         *
         * This implements the following type system shorthand:
         *   type Mutation {
         *     introduceShip(input IntroduceShipInput!): IntroduceShipPayload
         *   }
         */
        $mutationType = new ObjectType([
            'name' => 'Mutation',
            'fields' => function () use ($shipMutation) {
                return [
                    'introduceShip' => $shipMutation
                ];
            }
        ]);

        /**
         * Finally, we construct our schema (whose starting query type is the query
         * type we defined above) and export it.
         */
        $schema = new Schema([
            'query' => $queryType,
            'mutation' => $mutationType
        ]);

        return $schema;
    }
}