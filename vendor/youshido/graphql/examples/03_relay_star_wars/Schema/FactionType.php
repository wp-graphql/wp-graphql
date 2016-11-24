<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/10/16 11:53 PM
*/

namespace Examples\StarWars;


use Youshido\GraphQL\Relay\Connection\ArrayConnection;
use Youshido\GraphQL\Relay\Connection\Connection;
use Youshido\GraphQL\Relay\Field\GlobalIdField;
use Youshido\GraphQL\Relay\NodeInterfaceType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\TypeMap;

class FactionType extends AbstractObjectType
{

    const TYPE_KEY = 'faction';

    public function build($config)
    {
        $config
            ->addField(new GlobalIdField(self::TYPE_KEY))
            ->addField('factionId', [
                'type' => new IntType(),
                'resolve' => function($value) {
                    return $value['id'];
                }
            ])
            ->addField('name', [
                'type'        => TypeMap::TYPE_STRING,
                'description' => 'The name of the faction.'
            ])
            ->addField('ships', [
                'type'        => Connection::connectionDefinition(new ShipType()),
                'description' => 'The ships used by the faction',
                'args'        => Connection::connectionArgs(),
                'resolve'     => function ($value = null, $args = [], $type = null) {
                    return ArrayConnection::connectionFromArray(array_map(function ($id) {
                        return TestDataProvider::getShip($id);
                    }, $value['ships']), $args);
                }
            ]);

    }

    public function getDescription()
    {
        return 'A faction in the Star Wars saga';
    }

    public function getOne($id)
    {
        return TestDataProvider::getFaction($id);
    }

    public function getInterfaces()
    {
        return [new NodeInterfaceType()];
    }

}
