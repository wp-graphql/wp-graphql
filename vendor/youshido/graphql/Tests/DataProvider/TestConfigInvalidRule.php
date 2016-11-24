<?php

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Type\TypeService;

class TestConfigInvalidRule extends AbstractConfig
{
    public function getRules()
    {
        return [
            'name'             => ['type' => TypeService::TYPE_ANY, 'required' => true],
            'invalidRuleField' => ['type' => TypeService::TYPE_ANY, 'invalid rule' => true]
        ];
    }

}
