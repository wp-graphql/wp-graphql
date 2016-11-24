<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/1/15 11:13 PM
*/

namespace Youshido\GraphQL\Config\Object;


use Youshido\GraphQL\Type\TypeService;

class InputObjectTypeConfig extends ObjectTypeConfig
{
    public function getRules()
    {
        return [
            'name'        => ['type' => TypeService::TYPE_STRING, 'required' => true],
            'fields'      => ['type' => TypeService::TYPE_ARRAY_OF_INPUT_FIELDS, 'final' => true],
            'description' => ['type' => TypeService::TYPE_STRING],
        ];
    }
}
