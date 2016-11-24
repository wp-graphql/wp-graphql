<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/15/16 3:19 PM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Type\ListType\AbstractListType;
use Youshido\GraphQL\Type\Scalar\StringType;

class TestListType extends AbstractListType
{
    public function getItemType()
    {
        return new StringType();
    }


}
