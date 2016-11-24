<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 4:16 PM 5/13/16
 */

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Type\InputObject\AbstractInputObjectType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TestInputObjectType extends AbstractInputObjectType
{
    public function build($config)
    {
        $config->addField('name', new NonNullType(new StringType()));
    }

}
