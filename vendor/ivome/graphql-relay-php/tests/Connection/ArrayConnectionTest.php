<?php
/**
 * @author: Ivo Meißner
 * Date: 23.02.16
 * Time: 17:04
 */

namespace GraphQLRelay\Tests\Connection;

use GraphQLRelay\Connection\ArrayConnection;

class ArrayConnectionTest extends \PHPUnit_Framework_TestCase
{
    protected $letters = ['A', 'B', 'C', 'D', 'E'];

    public function testReturnsAllElementsWithoutFilters()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, []);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'A',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                        ),
                    1 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    2 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    3 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    4 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsASmallerFirst()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, ['first' => 2]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'A',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                        ),
                    1 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsAnOverlyLargeFirst()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, ['first' => 10]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'A',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                        ),
                    1 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    2 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    3 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    4 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsASmallerLast()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, ['last' => 2]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    1 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => true,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsAnOverlyLargeLast()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, ['last' => 10]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'A',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                        ),
                    1 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    2 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    3 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    4 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsFirstAndAfter()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, ['first' => 2, 'after' => 'YXJyYXljb25uZWN0aW9uOjE=']);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    1 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($connection, $expected);
    }

    public function testRespectsFirstAndAfterWithLongFirst()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, ['first' => 10, 'after' => 'YXJyYXljb25uZWN0aW9uOjE=']);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    1 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    2 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsLastAndBefore()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'last' => 2,
            'before' => 'YXJyYXljb25uZWN0aW9uOjM='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'hasPreviousPage' => true,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsLastAndBeforeWithLongLast()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'last' => 10,
            'before' => 'YXJyYXljb25uZWN0aW9uOjM='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'A',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                        ),
                    1 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    2 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsFirstAndAfterAndBeforeTooFew()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'first' => 2,
            'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            'before' => 'YXJyYXljb25uZWN0aW9uOjQ='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsFirstAndAfterAndBeforeTooMany()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'first' => 3,
            'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            'before' => 'YXJyYXljb25uZWN0aW9uOjQ='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    2 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsFirstAndAfterAndBeforeExactlyRight()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'first' => 3,
            'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            'before' => 'YXJyYXljb25uZWN0aW9uOjQ='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    2 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsLastAndAfterAndBeforeTooFew()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'last' => 2,
            'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            'before' => 'YXJyYXljb25uZWN0aW9uOjQ='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    1 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => true,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsLastAndAfterAndBeforeTooMany()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'last' => 4,
            'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            'before' => 'YXJyYXljb25uZWN0aW9uOjQ='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    2 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testRespectsLastAndAfterAndBeforeExactlyRight()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'last' => 3,
            'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            'before' => 'YXJyYXljb25uZWN0aW9uOjQ='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    2 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testReturnsNoElementsIfFirstIs0()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'first' => 0
        ]);

        $expected = array (
            'edges' =>
                array (
                ),
            'pageInfo' =>
                array (
                    'startCursor' => NULL,
                    'endCursor' => NULL,
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testReturnsAllElementsIfCursorsAreInvalid()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'before' => 'invalid',
            'after' => 'invalid'
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'A',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                        ),
                    1 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    2 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    3 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    4 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testReturnsAllElementsIfCursorsAreOnTheOutside()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'before' => 'YXJyYXljb25uZWN0aW9uOjYK',
            'after' => 'YXJyYXljb25uZWN0aW9uOi0xCg=='
        ]);

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'A',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                        ),
                    1 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    2 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    3 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    4 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testReturnsNoElementsIfCursorsCross()
    {
        $connection = ArrayConnection::connectionFromArray($this->letters, [
            'before' => 'YXJyYXljb25uZWN0aW9uOjI=',
            'after' => 'YXJyYXljb25uZWN0aW9uOjQ='
        ]);

        $expected = array (
            'edges' =>
                array (
                ),
            'pageInfo' =>
                array (
                    'startCursor' => NULL,
                    'endCursor' => NULL,
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testReturnsAnEdgeCursorGivenAnArrayAndAMemberObject()
    {
        $cursor = ArrayConnection::cursorForObjectInConnection($this->letters, 'B');

        $this->assertEquals('YXJyYXljb25uZWN0aW9uOjE=', $cursor);
    }

    public function testReturnsNullGivenAnArrayAndANonMemberObject()
    {
        $cursor = ArrayConnection::cursorForObjectInConnection($this->letters, 'F');

        $this->assertEquals(null, $cursor);
    }

    public function testWorksWithAJustRightArraySlice()
    {
        $connection = ArrayConnection::connectionFromArraySlice(
            array_slice($this->letters, 1, 2),
            [
                'first' => 2,
                'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            ],
            [
                'sliceStart' => 1,
                'arrayLength' => 5
            ]
        );

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testWorksWithAnOversizedArraySliceLeftSide()
    {
        $connection = ArrayConnection::connectionFromArraySlice(
            array_slice($this->letters, 0, 3),
            [
                'first' => 2,
                'after' => 'YXJyYXljb25uZWN0aW9uOjA=',
            ],
            [
                'sliceStart' => 0,
                'arrayLength' => 5
            ]
        );

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'B',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                        ),
                    1 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testWorksWithAnOversizedArraySliceRightSide()
    {
        $connection = ArrayConnection::connectionFromArraySlice(
            array_slice($this->letters, 2, 2),
            [
                'first' => 1,
                'after' => 'YXJyYXljb25uZWN0aW9uOjE=',
            ],
            [
                'sliceStart' => 2,
                'arrayLength' => 5
            ]
        );

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testWorksWithAnOversizedArraySliceBothSides()
    {
        $connection = ArrayConnection::connectionFromArraySlice(
            array_slice($this->letters, 1, 3),
            [
                'first' => 1,
                'after' => 'YXJyYXljb25uZWN0aW9uOjE=',
            ],
            [
                'sliceStart' => 1,
                'arrayLength' => 5
            ]
        );

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testWorksWithAnUndersizedArraySliceLeftSide()
    {
        $connection = ArrayConnection::connectionFromArraySlice(
            array_slice($this->letters, 3, 2),
            [
                'first' => 3,
                'after' => 'YXJyYXljb25uZWN0aW9uOjE=',
            ],
            [
                'sliceStart' => 3,
                'arrayLength' => 5
            ]
        );

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                    1 =>
                        array (
                            'node' => 'E',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => false,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testWorksWithAnUndersizedArraySliceRightSide()
    {
        $connection = ArrayConnection::connectionFromArraySlice(
            array_slice($this->letters, 2, 2),
            [
                'first' => 3,
                'after' => 'YXJyYXljb25uZWN0aW9uOjE=',
            ],
            [
                'sliceStart' => 2,
                'arrayLength' => 5
            ]
        );

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'C',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                        ),
                    1 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }

    public function testWorksWithAnUndersizedArraySliceBothSides()
    {
        $connection = ArrayConnection::connectionFromArraySlice(
            array_slice($this->letters, 3, 1),
            [
                'first' => 3,
                'after' => 'YXJyYXljb25uZWN0aW9uOjE=',
            ],
            [
                'sliceStart' => 3,
                'arrayLength' => 5
            ]
        );

        $expected = array (
            'edges' =>
                array (
                    0 =>
                        array (
                            'node' => 'D',
                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                        ),
                ),
            'pageInfo' =>
                array (
                    'startCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'endCursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                    'hasPreviousPage' => false,
                    'hasNextPage' => true,
                ),
        );

        $this->assertEquals($expected, $connection);
    }
}
