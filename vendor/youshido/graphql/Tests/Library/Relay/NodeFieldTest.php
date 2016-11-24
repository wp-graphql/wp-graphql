<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/17/16 10:29 PM
*/

namespace Youshido\Tests\Library\Relay;


use Youshido\GraphQL\Relay\Fetcher\CallableFetcher;
use Youshido\GraphQL\Relay\Field\NodeField;

class NodeFieldTest extends \PHPUnit_Framework_TestCase
{

    public function testMethods()
    {
        $fetcher = new CallableFetcher(function () { }, function () { });
        $field   = new NodeField($fetcher);

        $this->assertEquals('Fetches an object given its ID', $field->getDescription());
        $this->assertEquals('node', $field->getName());
        $this->assertEquals($fetcher, $field->getType()->getFetcher());
    }
}
