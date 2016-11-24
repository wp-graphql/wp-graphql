<?php
/*
 * This file is a part of GraphQL project.
 *
 * @author Alexandr Viniychuk <a@viniychuk.com>
 * created: 4:33 PM 5/18/16
 */

namespace Youshido\Tests\Library\Relay;

use Youshido\GraphQL\Relay\Connection\ArrayConnection;

class ArrayConnectionTest extends \PHPUnit_Framework_TestCase
{
    public function testCursors()
    {
        $offset = 3;
        $data   = ['a', 'b', 'c', 'd', 'e'];
        $cursor = ArrayConnection::offsetToCursor($offset);

        $this->assertEquals($offset, ArrayConnection::cursorToOffset($cursor));
        $this->assertEquals($cursor, ArrayConnection::cursorForObjectInConnection($data, 'd'));
        $this->assertNull(null, ArrayConnection::cursorToOffset(null));

        $this->assertEquals($offset, ArrayConnection::cursorToOffsetWithDefault($cursor, 2));
        $this->assertEquals(2, ArrayConnection::cursorToOffsetWithDefault(null, 2));
    }

    public function testConnectionDefinition()
    {
        $data  = ['a', 'b', 'c', 'd', 'e'];
        $edges = [];

        foreach ($data as $key => $item) {
            $edges[] = ArrayConnection::edgeForObjectWithIndex($item, $key);
        }

        $this->assertEquals([
            'edges'    => $edges,
            'pageInfo' => [
                'startCursor'     => $edges[0]['cursor'],
                'endCursor'       => $edges[count($edges) - 1]['cursor'],
                'hasPreviousPage' => false,
                'hasNextPage'     => false
            ]
        ], ArrayConnection::connectionFromArray($data));

        $this->assertEquals([
            'edges'    => array_slice($edges, 0, 2),
            'pageInfo' => [
                'startCursor'     => $edges[0]['cursor'],
                'endCursor'       => $edges[1]['cursor'],
                'hasPreviousPage' => false,
                'hasNextPage'     => true
            ]
        ], ArrayConnection::connectionFromArray($data, ['first' => 2, 'last' => 4]));
    }
}
