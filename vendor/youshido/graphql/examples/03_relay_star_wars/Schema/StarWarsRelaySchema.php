<?php

namespace Examples\StarWars;

use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Field\InputField;
use Youshido\GraphQl\Relay\Connection\ArrayConnection;
use Youshido\GraphQL\Relay\Connection\Connection;
use Youshido\GraphQL\Relay\Fetcher\CallableFetcher;
use Youshido\GraphQL\Relay\Field\NodeField;
use Youshido\GraphQL\Relay\RelayMutation;
use Youshido\GraphQL\Schema\AbstractSchema;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\StringType;

class StarWarsRelaySchema extends AbstractSchema
{
    public function build(SchemaConfig $config)
    {
        $fetcher = new CallableFetcher(
            function ($type, $id) {
                switch ($type) {
                    case FactionType::TYPE_KEY:
                        return TestDataProvider::getFaction($id);

                    case
                    ShipType::TYPE_KEY:
                        return TestDataProvider::getShip($id);
                }

                return null;
            },
            function ($object) {
                return $object && array_key_exists('ships', $object) ? new FactionType() : new ShipType();
            }
        );

        $config->getQuery()
               ->addField(new NodeField($fetcher))
               ->addField('rebels', [
                   'type'    => new FactionType(),
                   'resolve' => function () {
                       return TestDataProvider::getFaction('rebels');
                   }
               ])
               ->addField('empire', [
                   'type'    => new FactionType(),
                   'resolve' => function () {
                       return TestDataProvider::getFaction('empire');
                   }
               ])
               ->addField('factions', [
                   'type'    => new ListType(new FactionType()),
                   'args'    => [
                       'names' => [
                           'type' => new ListType(new StringType())
                       ]
                   ],
                   'resolve' => function ($value = null, $args, $info) {
                       return TestDataProvider::getByNames($args['names']);
                   }
               ]);


        $config->getMutation()
               ->addField(
                   RelayMutation::buildMutation(
                       'introduceShip',
                       [
                           new InputField(['name' => 'shipName', 'type' => new NonNullType(new StringType())]),
                           new InputField(['name' => 'factionId', 'type' => new NonNullType(new StringType())])
                       ],
                       [
                           'newShipEdge'    => [
                               'type'    => Connection::edgeDefinition(new ShipType(), 'newShip'),
                               'resolve' => function ($value) {
                                   $allShips = TestDataProvider::getShips();
                                   $newShip  = TestDataProvider::getShip($value['shipId']);

                                   return [
                                       'cursor' => ArrayConnection::cursorForObjectInConnection($allShips, $newShip),
                                       'node' => $newShip
                                   ];
                               }
                           ],
                           'faction' => [
                               'type'    => new FactionType(),
                               'resolve' => function ($value) {
                                   return TestDataProvider::getFaction($value['factionId']);
                               }
                           ]
                       ],
                       function ($value, $args, $info) {
                           $newShip = TestDataProvider::createShip($args['shipName'], $args['factionId']);

                           return [
                               'shipId'    => $newShip['id'],
                               'factionId' => $args['factionId']
                           ];
                       }
                   )
               );

        /** https://github.com/graphql/graphql-relay-js/blob/master/src/__tests__/starWarsSchema.js */
    }

}
