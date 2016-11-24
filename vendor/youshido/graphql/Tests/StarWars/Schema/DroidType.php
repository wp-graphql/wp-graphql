<?php
/**
 * Date: 07.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\Tests\StarWars\Schema;


use Youshido\GraphQL\Type\TypeMap;

class DroidType extends HumanType
{

    /**
     * @return String type name
     */
    public function getName()
    {
        return 'Droid';
    }

    public function build($config)
    {
        parent::build($config);

        $config->getField('friends')->getConfig()->set('resolve', function ($droid) {
            return StarWarsData::getFriends($droid);
        });

        $config
            ->addField('primaryFunction', TypeMap::TYPE_STRING);
    }

    public function getInterfaces()
    {
        return [new CharacterInterface()];
    }
}
