<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 9:27 PM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Type\TypeService;

class TestConfigExtraFields extends AbstractConfig
{

    protected $extraFieldsAllowed = true;

    public function getRules()
    {
        return [
            'name' => ['type' => TypeService::TYPE_ANY, 'required' => true]
        ];
    }


}
