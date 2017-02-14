<?php
/**
 * @author: Ivo Meißner
 * Date: 29.02.16
 * Time: 11:23
 */

namespace GraphQLRelay\tests;


use GraphQL\GraphQL;

class StarWarsObjectIdentificationTest extends \PHPUnit_Framework_TestCase
{
    public function testFetchesTheIDAndNameOfTheRebels()
    {
        $query = 'query RebelsQuery {
            rebels {
              id
              name
            }
          }';

        $expected = array (
            'rebels' =>
                array (
                    'id' => 'RmFjdGlvbjox',
                    'name' => 'Alliance to Restore the Republic',
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testRefetchesTheRebels()
    {
        $query = 'query RebelsRefetchQuery {
            node(id: "RmFjdGlvbjox") {
              id
              ... on Faction {
                name
              }
            }
          }';

        $expected = array (
            'node' =>
                array (
                    'id' => 'RmFjdGlvbjox',
                    'name' => 'Alliance to Restore the Republic',
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testFetchesTheIDAndNameOfTheEmpire()
    {
        $query = 'query EmpireQuery {
            empire {
              id
              name
            }
          }';

        $expected = array (
            'empire' =>
                array (
                    'id' => 'RmFjdGlvbjoy',
                    'name' => 'Galactic Empire',
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testRefetchesTheEmpire()
    {
        $query = 'query EmpireRefetchQuery {
            node(id: "RmFjdGlvbjoy") {
              id
              ... on Faction {
                name
              }
            }
          }';

        $expected = array (
            'node' =>
                array (
                    'id' => 'RmFjdGlvbjoy',
                    'name' => 'Galactic Empire',
                ),
        );

        $this->assertValidQuery($query, $expected);
    }

    public function testRefetchesTheXWing()
    {
        $query = 'query XWingRefetchQuery {
            node(id: "U2hpcDox") {
              id
              ... on Ship {
                name
              }
            }
          }';

        $expected = array (
            'node' =>
                array (
                    'id' => 'U2hpcDox',
                    'name' => 'X-Wing',
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