<?php
/**
 * Date: 07.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\Tests\StarWars\Schema;


use Youshido\GraphQL\Config\Schema\SchemaConfig;
use Youshido\GraphQL\Schema\AbstractSchema;

class StarWarsSchema extends AbstractSchema
{

    public function build(SchemaConfig $config)
    {
        $config->setQuery(new StarWarsQueryType());
    }

}
