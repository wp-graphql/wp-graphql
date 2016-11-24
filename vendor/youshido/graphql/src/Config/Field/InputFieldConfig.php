<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/1/15 11:28 PM
*/

namespace Youshido\GraphQL\Config\Field;


use Youshido\GraphQL\Config\AbstractConfig;
use Youshido\GraphQL\Type\TypeService;

class InputFieldConfig extends AbstractConfig
{

    public function getRules()
    {
        return [
            'name'        => ['type' => TypeService::TYPE_STRING, 'final' => true],
            'type'        => ['type' => TypeService::TYPE_ANY_INPUT, 'final' => true],
            'default'     => ['type' => TypeService::TYPE_ANY],
            'description' => ['type' => TypeService::TYPE_STRING],
        ];
    }

    public function getDefaultValue()
    {
        return $this->get('default');
    }

}
