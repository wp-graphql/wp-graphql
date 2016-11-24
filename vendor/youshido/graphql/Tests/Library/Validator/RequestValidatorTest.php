<?php
/**
 * Date: 27.10.16
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\Tests\Library\Validator;


use Youshido\GraphQL\Execution\Request;
use Youshido\GraphQL\Parser\Ast\Argument;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Variable;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\VariableReference;
use Youshido\GraphQL\Parser\Ast\Field;
use Youshido\GraphQL\Parser\Ast\Fragment;
use Youshido\GraphQL\Parser\Ast\FragmentReference;
use Youshido\GraphQL\Parser\Ast\Query;
use Youshido\GraphQL\Validator\RequestValidator\RequestValidator;

class RequestValidatorTest extends \PHPUnit_Framework_TestCase
{

    /**
     * @expectedException Youshido\GraphQL\Parser\Exception\InvalidRequestException
     * @dataProvider invalidRequestProvider
     *
     * @param Request $request
     */
    public function testInvalidRequests(Request $request)
    {
        (new RequestValidator())->validate($request);
    }

    public function invalidRequestProvider()
    {
        $variable1 = (new Variable('test', 'Int'))->setUsed(true);
        $variable2 = (new Variable('test2', 'Int'))->setUsed(true);
        $variable3 = (new Variable('test3', 'Int'))->setUsed(false);

        return [
            [
                new Request([
                    'queries'            => [
                        new Query('test', null, [], [
                            new FragmentReference('reference')
                        ])
                    ],
                    'fragmentReferences' => [
                        new FragmentReference('reference')
                    ]
                ])
            ],
            [
                new Request([
                    'queries'            => [
                        new Query('test', null, [], [
                            new FragmentReference('reference'),
                            new FragmentReference('reference2'),
                        ])
                    ],
                    'fragments'          => [
                        new Fragment('reference', 'TestType', [])
                    ],
                    'fragmentReferences' => [
                        new FragmentReference('reference'),
                        new FragmentReference('reference2')
                    ]
                ])
            ],
            [
                new Request([
                    'queries'            => [
                        new Query('test', null, [], [
                            new FragmentReference('reference'),
                        ])
                    ],
                    'fragments'          => [
                        new Fragment('reference', 'TestType', []),
                        new Fragment('reference2', 'TestType', [])
                    ],
                    'fragmentReferences' => [
                        new FragmentReference('reference')
                    ]
                ])
            ],
            [
                new Request([
                    'queries'            => [
                        new Query('test', null, [
                            new Argument('test', new VariableReference('test'))
                        ], [
                            new Field('test')
                        ])
                    ],
                    'variableReferences' => [
                        new VariableReference('test')
                    ]
                ])
            ],
            [
                new Request([
                    'queries'            => [
                        new Query('test', null, [
                            new Argument('test', new VariableReference('test', $variable1)),
                            new Argument('test2', new VariableReference('test2', $variable2)),
                        ], [
                            new Field('test')
                        ])
                    ],
                    'variables'          => [
                        $variable1,
                        $variable2,
                        $variable3
                    ],
                    'variableReferences' => [
                        new VariableReference('test', $variable1),
                        new VariableReference('test2', $variable2)
                    ]
                ])
            ]
        ];
    }

}