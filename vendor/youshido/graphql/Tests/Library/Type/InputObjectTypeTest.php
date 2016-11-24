<?php
/*
* This file is a part of GraphQL project.
*
* @author Alexandr Viniychuk <a@viniychuk.com>
* created: 5/15/16 3:28 PM
*/

namespace Youshido\Tests\Library\Type;


use Youshido\GraphQL\Execution\Processor;
use Youshido\GraphQL\Schema\Schema;
use Youshido\GraphQL\Type\InputObject\InputObjectType;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\NonNullType;
use Youshido\GraphQL\Type\Object\ObjectType;
use Youshido\GraphQL\Type\Scalar\BooleanType;
use Youshido\GraphQL\Type\Scalar\StringType;
use Youshido\GraphQL\Type\TypeMap;
use Youshido\Tests\DataProvider\TestInputObjectType;

class InputObjectTypeTest extends \PHPUnit_Framework_TestCase
{

    public function testInternal()
    {
        $inputObjectType = new InputObjectType([
            'name'   => 'PostData',
            'fields' => [
                'title' => new NonNullType(new StringType()),
            ]
        ]);
        $this->assertEquals(TypeMap::KIND_INPUT_OBJECT, $inputObjectType->getKind());
        $this->assertEquals('PostData', $inputObjectType->getName());

        $this->assertFalse($inputObjectType->isValidValue('invalid value'));
        $this->assertTrue($inputObjectType->isValidValue(['title' => 'Super ball!']));
        $this->assertFalse($inputObjectType->isValidValue(['title' => null]));
    }

    public function testStandaloneClass()
    {
        $inputObjectType = new TestInputObjectType();
        $this->assertEquals('TestInputObject', $inputObjectType->getName());
    }

    public function testListOfInputWithNonNull()
    {
        $processor = new Processor(new Schema([
            'query'    => new ObjectType([
                'name'   => 'RootQuery',
                'fields' => [
                    'empty' => [
                        'type'    => new StringType(),
                        'resolve' => function () {
                            return null;
                        }
                    ]
                ]
            ]),
            'mutation' => new ObjectType([
                'name'   => 'RootMutation',
                'fields' => [
                    'createList' => [
                        'args'    => [
                            'posts' => new ListType(new InputObjectType([
                                'name'   => 'PostInputType',
                                'fields' => [
                                    'title' => new NonNullType(new StringType()),
                                ]
                            ]))
                        ],
                        'type'    => new BooleanType(),
                        'resolve' => function ($object, $args) {
                            return true;
                        }
                    ]
                ]
            ])
        ]));

        $processor->processPayload('mutation { createList(posts: [{title: null }, {}]) }');
        $a = $processor->getResponseData();
        $this->assertEquals(
            [
                'data'   => ['createList' => null],
                'errors' => [['message' => 'Not valid type for argument "posts" in query "createList"']]
            ],
            $processor->getResponseData()
        );
    }

    public function testListInsideInputObject()
    {
        $processor = new Processor(new Schema([
            'query'    => new ObjectType([
                'name'   => 'RootQueryType',
                'fields' => [
                    'empty' => [
                        'type'    => new StringType(),
                        'resolve' => function () {
                        }
                    ],
                ]
            ]),
            'mutation' => new ObjectType([
                'name'   => 'RootMutation',
                'fields' => [
                    'createList' => [
                        'type'    => new StringType(),
                        'args'    => [
                            'topArgument' => new InputObjectType([
                                'name'   => 'topArgument',
                                'fields' => [
                                    'postObject' => new ListType(new InputObjectType([
                                        'name'   => 'postObject',
                                        'fields' => [
                                            'title' => new NonNullType(new StringType()),
                                        ]
                                    ]))
                                ]
                            ])
                        ],
                        'resolve' => function () {
                            return 'success message';
                        }
                    ]
                ]
            ])
        ]));
        $processor->processPayload('mutation { createList(topArgument: { postObject:[ { title: null } ] })}');
        $this->assertEquals([
            'data'   => ['createList' => null],
            'errors' => [['message' => 'Not valid type for argument "topArgument" in query "createList"']],
        ], $processor->getResponseData());
        $processor->getExecutionContext()->clearErrors();
        $processor->processPayload('mutation { createList(topArgument:{
                                        postObject:[{title: "not empty"}] })}');
        $this->assertEquals(['data' => ['createList' => 'success message']], $processor->getResponseData());
    }

}
