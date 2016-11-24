<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/11/16 10:45 PM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Type\TypeService;

class TestConfig extends AbstractConfig
{
    public function getRules()
    {
        return [
            'name'    => ['type' => TypeService::TYPE_ANY, 'required' => true],
            'resolve' => ['type' => TypeService::TYPE_CALLABLE, 'final' => true],
        ];
    }

}
