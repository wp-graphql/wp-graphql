<?php
/**
 * @author: Ivo Meißner
 * Date: 29.02.16
 * Time: 17:01
 */

namespace GraphQLRelay\tests;

use GraphQL\Type\Definition\ObjectType;
use GraphQLRelay\Connection\Connection;
use GraphQLRelay\Relay;

class RelayTest extends \PHPUnit_Framework_TestCase
{
    public function testForwardConnectionArgs()
    {
        $this->assertEquals(
            Connection::forwardConnectionArgs(),
            Relay::forwardConnectionArgs()
        );
    }

    public function testBackwardConnectionArgs()
    {
        $this->assertEquals(
            Connection::backwardConnectionArgs(),
            Relay::backwardConnectionArgs()
        );
    }

    public function testConnectionArgs()
    {
        $this->assertEquals(
            Connection::connectionArgs(),
            Relay::connectionArgs()
        );
    }

    public function testConnectionDefinitions()
    {
        $nodeType = new ObjectType(['name' => 'test']);
        $config = ['nodeType' => $nodeType];

        $this->assertEquals(
            Connection::connectionDefinitions($config),
            Relay::connectionDefinitions($config)
        );
    }

    public function testConnectionType()
    {
        $nodeType = new ObjectType(['name' => 'test']);
        $config = ['nodeType' => $nodeType];

        $this->assertEquals(
            Connection::createConnectionType($config),
            Relay::connectionType($config)
        );
    }

    public function testEdgeType()
    {
        $nodeType = new ObjectType(['name' => 'test']);
        $config = ['nodeType' => $nodeType];

        $this->assertEquals(
            Connection::createEdgeType($config),
            Relay::edgeType($config)
        );
    }
}
