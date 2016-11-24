<?php
/*
* This file is a part of graphql-youshido project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 12/1/15 1:22 AM
*/

namespace Youshido\GraphQL\Config\Object;


use Youshido\GraphQL\Type\TypeService;

class ListTypeConfig extends ObjectTypeConfig
{
    public function getRules()
    {
        return [
            'itemType' => ['type' => TypeService::TYPE_GRAPHQL_TYPE, 'final' => true],
            'resolve'  => ['type' => TypeService::TYPE_CALLABLE],
            'args'     => ['type' => TypeService::TYPE_ARRAY_OF_INPUT_FIELDS],
        ];
    }

}
