<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/20/16 12:41 AM
*/

namespace Youshido\Tests\DataProvider;


use Youshido\GraphQL\Execution\Context\ExecutionContext;
use Youshido\GraphQL\Execution\ResolveInfo;

class TestResolveInfo
{
    public static function createTestResolveInfo($field = null)
    {
        if (empty($field)) {
            $field = new TestField();
        }

        return new ResolveInfo($field, [], new ExecutionContext(new TestSchema()));
    }
}
