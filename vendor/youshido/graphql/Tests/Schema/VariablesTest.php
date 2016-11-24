<?php

namespace Youshido\Tests\Schema;

use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\IdType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

class VariablesTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider queries
     *
     * @param $query
     * @param $expected
     * @param $variables
     */
    public function testVariables($query, $expected, $variables)
    {
        $schema = new Schema([
            'query' => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'stringQuery' => [
                        'type'    => new StringType(),
                        'args'    => [
                            'sortOrder' => new StringType(),
                        ],
                        'resolve' => function ($args) {
                            return sprintf('Result with %s order', empty($args['sortOrder']) ? 'default' : $args['sortOrder']);
                        },
                    ],
                ]
            ])
        ]);

        $processor = new Processor($schema);
        $processor->processPayload($query, $variables);
        $result = $processor->getResponseData();

        $this->assertEquals($expected, $result);
    }

    public function queries()
    {
        return [
            [
                'query ListQuery($sort:String) {
                  stringQuery(sortOrder: $sort)
                }',
                [
                    'data' => [
                        'stringQuery' => 'Result with default order'
                    ],
                ],
                [
                    'sort' => null
                ]
            ],
            [
                'query queryWithVariable($abc:String) {
                  stringQuery {
                    ...someFragment
                  }
                }',
                [
                    'errors' => [
                        ['message' => 'Fragment "someFragment" not defined in query']
                    ],
                ],
                [
                    'abc' => null
                ]
            ],
        ];
    }

}