<?php
/**
 * @author: Ivo Meißner
 * Date: 29.02.16
 * Time: 12:18
 */

namespace GraphQLRelay\tests;


use GraphQL\GraphQL;

class StarWarsConnectionTest extends \PHPUnit_Framework_TestCase
{
    public function testFetchesTheFirstShipOfTheRebels()
    {
        $query = 'query RebelsShipsQuery {
            rebels {
              name,
              ships(first: 1) {
                edges {
                  node {
                    name
                  }
                }
              }
            }
          }';

        $expected = array (
            'rebels' =>
                array (
                    'name' => 'Alliance to Restore the Republic',
                    'ships' =>
                        array (
                            'edges' =>
                                array (
                                    0 =>
                                        array (
                                            'node' =>
                                                array (
                                                    'name' => 'X-Wing',
                                                ),
                                        ),
                                ),
                        ),
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testFetchesTheFirstTwoShipsOfTheRebelsWithACursor()
    {
        $query = 'query MoreRebelShipsQuery {
            rebels {
              name,
              ships(first: 2) {
                edges {
                  cursor,
                  node {
                    name
                  }
                }
              }
            }
          }';

        $expected = array (
            'rebels' =>
                array (
                    'name' => 'Alliance to Restore the Republic',
                    'ships' =>
                        array (
                            'edges' =>
                                array (
                                    0 =>
                                        array (
                                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjA=',
                                            'node' =>
                                                array (
                                                    'name' => 'X-Wing',
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjE=',
                                            'node' =>
                                                array (
                                                    'name' => 'Y-Wing',
                                                ),
                                        ),
                                ),
                        ),
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testFetchesTheNextThreeShipsOfTHeRebelsWithACursor()
    {
        $query = 'query EndOfRebelShipsQuery {
            rebels {
              name,
              ships(first: 3 after: "YXJyYXljb25uZWN0aW9uOjE=") {
                edges {
                  cursor,
                  node {
                    name
                  }
                }
              }
            }
          }';

        $expected = array (
            'rebels' =>
                array (
                    'name' => 'Alliance to Restore the Republic',
                    'ships' =>
                        array (
                            'edges' =>
                                array (
                                    0 =>
                                        array (
                                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjI=',
                                            'node' =>
                                                array (
                                                    'name' => 'A-Wing',
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjM=',
                                            'node' =>
                                                array (
                                                    'name' => 'Millenium Falcon',
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'cursor' => 'YXJyYXljb25uZWN0aW9uOjQ=',
                                            'node' =>
                                                array (
                                                    'name' => 'Home One',
                                                ),
                                        ),
                                ),
                        ),
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testFetchesNoShipsOfTheRebelsAtTheEndOfConnection()
    {
        $query = 'query RebelsQuery {
            rebels {
              name,
              ships(first: 3 after: "YXJyYXljb25uZWN0aW9uOjQ=") {
                edges {
                  cursor,
                  node {
                    name
                  }
                }
              }
            }
          }';

        $expected = array (
            'rebels' =>
                array (
                    'name' => 'Alliance to Restore the Republic',
                    'ships' =>
                        array (
                            'edges' =>
                                array (
                                ),
                        ),
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testIdentifiesTheEndOfTheList()
    {
        $query = 'query EndOfRebelShipsQuery {
            rebels {
              name,
              originalShips: ships(first: 2) {
                edges {
                  node {
                    name
                  }
                }
                pageInfo {
                  hasNextPage
                }
              }
              moreShips: ships(first: 3 after: "YXJyYXljb25uZWN0aW9uOjE=") {
                edges {
                  node {
                    name
                  }
                }
                pageInfo {
                  hasNextPage
                }
              }
            }
          }';
        $expected = array (
            'rebels' =>
                array (
                    'name' => 'Alliance to Restore the Republic',
                    'originalShips' =>
                        array (
                            'edges' =>
                                array (
                                    0 =>
                                        array (
                                            'node' =>
                                                array (
                                                    'name' => 'X-Wing',
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'node' =>
                                                array (
                                                    'name' => 'Y-Wing',
                                                ),
                                        ),
                                ),
                            'pageInfo' =>
                                array (
                                    'hasNextPage' => true,
                                ),
                        ),
                    'moreShips' =>
                        array (
                            'edges' =>
                                array (
                                    0 =>
                                        array (
                                            'node' =>
                                                array (
                                                    'name' => 'A-Wing',
                                                ),
                                        ),
                                    1 =>
                                        array (
                                            'node' =>
                                                array (
                                                    'name' => 'Millenium Falcon',
                                                ),
                                        ),
                                    2 =>
                                        array (
                                            'node' =>
                                                array (
                                                    'name' => 'Home One',
                                                ),
                                        ),
                                ),
                            'pageInfo' =>
                                array (
                                    'hasNextPage' => false,
                                ),
                        ),
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    /**
     * Helper function to test a query and the expected response.
     */
    private function assertValidQuery($query, $expected)
    {
        $result = GraphQL::execute(StarWarsSchema::getSchema(), $query);

        $this->assertEquals(['data' => $expected], $result);
    }
}