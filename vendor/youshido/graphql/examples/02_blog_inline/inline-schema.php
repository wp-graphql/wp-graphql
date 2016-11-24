<?php
namespace BlogTest;

use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

$rootQueryType = new ObjectType([
    'name'   => 'RootQueryType',
    'fields' => [
        'latestPost' => [
            'type'    => new ObjectType([
                // you have to specify a string name
                'name'   => 'Post',
                // fields is an array of the array structure
                'fields' => [
                    // here you have a complex field with a lot of options
                    'title'     => [
                        'type'              => new StringType(),                    // string type
                        'description'       => 'This field contains a post title',  // description
                        'isDeprecated'      => true,                                // marked as deprecated
                        'deprecationReason' => 'field title is now deprecated',     // explain the reason
                        'args'              => [
                            'truncated' => new BooleanType()                        // add an optional argument
                        ],
                        'resolve'           => function ($value, $args) {
                            // used argument to modify a field value
                            return (!empty($args['truncated'])) ? explode(' ', $value)[0] . '...' : $value;
                        }
                    ],
                    // if field just has a type, you can use a short declaration syntax like this
                    'summary'   => new StringType(),
                    'likeCount' => new IntType(),
                ],
            ]),
            // arguments for the whole query
            'args'    => [
                'id' => new IntType()
            ],
            // resolve function for the query
            'resolve' => function () {
                return [
                    'title'   => 'Title for the latest Post',
                    'summary' => 'Post summary',
                ];
            }
        ]
    ]
]);
