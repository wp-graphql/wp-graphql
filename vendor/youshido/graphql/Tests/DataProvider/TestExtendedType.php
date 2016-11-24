<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 8/14/16 1:19 PM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Config\Object\ObjectTypeConfig;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TestExtendedType extends AbstractObjectType
{
    public function build($config)
    {
        $config->applyInterface(new TestInterfaceType())
            ->addField('ownField', new StringType());
    }


}