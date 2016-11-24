<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/12/16 6:42 PM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Type\Enum\AbstractEnumType;

class TestEnumType extends AbstractEnumType
{
    public function getValues()
    {
        return [
            [
                'name'  => 'FINISHED',
                'value' => 1,
            ],
            [
                'name'  => 'NEW',
                'value' => 0,
            ]
        ];
    }

}
