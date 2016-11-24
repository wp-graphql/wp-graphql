<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 4:24 PM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Type\InterfaceType\AbstractInterfaceType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TestInterfaceType extends AbstractInterfaceType
{

    public function resolveType($object)
    {
        return is_object($object) ? $object : new TestObjectType();
    }

    public function build($config)
    {
        $config->addField('name', new StringType());
    }


}
