<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/17/16 11:56 AM
*/

namespace Youshido\Tests\Library\Relay;


use Youshido\GraphQL\Relay\Fetcher\CallableFetcher;
use Youshido\Tests\DataProvider\TestObjectType;

class CallableFetcherTest extends \PHPUnit_Framework_TestCase
{
    public function testMethods()
    {
        $fetcher = new CallableFetcher(function ($type, $id) { return ['name' => $type . ' Name', 'id' => $id]; }, function ($object) { return $object; });
        $this->assertEquals([
            'name' => 'User Name',
            'id'   => 12
        ], $fetcher->resolveNode('User', 12));

        $object = new TestObjectType();
        $this->assertEquals($object, $fetcher->resolveType($object));
    }

}
