<?php
/**
 * Date: 01.12.15
 *
 * @author Portey Vasil <portey@gmail.com>
 */

namespace Youshido\Tests\Parser;

use Youshido\GraphQL\Parser\Ast\Argument;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputList;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\InputObject;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Literal;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\Variable;
use Youshido\GraphQL\Parser\Ast\ArgumentValue\VariableReference;
use Youshido\GraphQL\Parser\Ast\Field;
use Youshido\GraphQL\Parser\Ast\Fragment;
use Youshido\GraphQL\Parser\Ast\FragmentReference;
use Youshido\GraphQL\Parser\Ast\Mutation;
use Youshido\GraphQL\Parser\Ast\Query;
use Youshido\GraphQL\Parser\Ast\TypedFragmentReference;
use Youshido\GraphQL\Parser\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{

    public function testEmptyParser()
    {
        $parser = new Parser();

        $this->assertEquals([
            'queries'            => [],
            'mutations'          => [],
            'fragments'          => [],
            'fragmentReferences' => [],
            'variables'          => [],
            'variableReferences' => [],
        ], $parser->parse());
    }

    /**
     * @expectedException Youshido\GraphQL\Parser\Exception\SyntaxErrorException
     */
    public function testInvalidSelection()
    {
        $parser = new Parser();
        $data = $parser->parse('
        {
            test {
                id
                image {
                    
                }
            }
        }
        ');
    }

    public function testComments()
    {
        $query = '
            # asdasd "asdasdasd"
            # comment line 2

            query {
              authors (category: "#2") { #asda asd
                _id
              }
            }
        ';


        $parser = new Parser();

        $this->assertEquals($parser->parse($query), [
            'queries'            => [
                new Query('authors', null,
                    [
                        new Argument('category', new Literal('#2'))
                    ],
                    [
                        new Field('_id', null),
                    ])
            ],
            'mutations'          => [],
            'fragments'          => [],
            'fragmentReferences' => [],
            'variables'          => [],
            'variableReferences' => []
        ]);
    }


    /**
     * @param $query string
     *
     * @dataProvider wrongQueriesProvider
     * @expectedException Youshido\GraphQL\Parser\Exception\SyntaxErrorException
     */
    public function testWrongQueries($query)
    {
        $parser = new Parser();

        $parser->parse($query);
    }

    public function testQueryWithNoFields()
    {
        $parser = new Parser();
        $data   = $parser->parse('{ name }');
        $this->assertEquals([
            'queries'            => [
                new Query('name')
            ],
            'mutations'          => [],
            'fragments'          => [],
            'fragmentReferences' => [],
            'variables'          => [],
            'variableReferences' => [],
        ], $data);
    }

    public function testQueryWithFields()
    {
        $parser = new Parser();
        $data   = $parser->parse('{ post, user { name } }');
        $this->assertEquals([
            'queries'            => [
                new Query('post'),
                new Query('user', null, [], [
                    new Field('name')
                ])
            ],
            'mutations'          => [],
            'fragments'          => [],
            'fragmentReferences' => [],
            'variables'          => [],
            'variableReferences' => [],
        ], $data);
    }

    public function testFragmentWithFields()
    {
        $parser = new Parser();
        $data   = $parser->parse('
            fragment FullType on __Type {
                kind
                fields {
                    name
                }
            }');
        $this->assertEquals([
            'queries'            => [],
            'mutations'          => [],
            'fragments'          => [
                new Fragment('FullType', '__Type', [
                    new Field('kind'),
                    new Query('fields', null, [], [
                        new Field('name')
                    ])
                ])
            ],
            'fragmentReferences' => [],
            'variables'          => [],
            'variableReferences' => [],
        ], $data);
    }

    public function testInspectionQuery()
    {
        $parser = new Parser();

        $data = $parser->parse('
            query IntrospectionQuery {
                __schema {
                    queryType { name }
                    mutationType { name }
                    types {
                        ...FullType
                    }
                    directives {
                        name
                        description
                        args {
                            ...InputValue
                        }
                        onOperation
                        onFragment
                        onField
                    }
                }
            }

            fragment FullType on __Type {
                kind
                name
                description
                fields {
                    name
                    description
                    args {
                        ...InputValue
                    }
                    type {
                        ...TypeRef
                    }
                    isDeprecated
                    deprecationReason
                }
                inputFields {
                    ...InputValue
                }
                interfaces {
                    ...TypeRef
                }
                enumValues {
                    name
                    description
                    isDeprecated
                    deprecationReason
                }
                possibleTypes {
                    ...TypeRef
                }
            }

            fragment InputValue on __InputValue {
                name
                description
                type { ...TypeRef }
                defaultValue
            }

            fragment TypeRef on __Type {
                kind
                name
                ofType {
                    kind
                    name
                    ofType {
                        kind
                        name
                        ofType {
                            kind
                            name
                        }
                    }
                }
            }
        ');

        $this->assertEquals([
            'queries'            => [
                new Query('__schema', null, [], [
                    new Query('queryType', null, [], [
                        new Field('name')
                    ]),
                    new Query('mutationType', null, [], [
                        new Field('name')
                    ]),
                    new Query('types', null, [], [
                        new FragmentReference('FullType')
                    ]),
                    new Query('directives', null, [], [
                        new Field('name'),
                        new Field('description'),
                        new Query('args', null, [], [
                            new FragmentReference('InputValue'),
                        ]),
                        new Field('onOperation'),
                        new Field('onFragment'),
                        new Field('onField'),
                    ]),
                ])
            ],
            'mutations'          => [],
            'fragments'          => [
                new Fragment('FullType', '__Type', [
                    new Field('kind'),
                    new Field('name'),
                    new Field('description'),
                    new Query('fields', null, [], [
                        new Field('name'),
                        new Field('description'),
                        new Query('args', null, [], [
                            new FragmentReference('InputValue'),
                        ]),
                        new Query('type', null, [], [
                            new FragmentReference('TypeRef'),
                        ]),
                        new Field('isDeprecated'),
                        new Field('deprecationReason'),
                    ]),
                    new Query('inputFields', null, [], [
                        new FragmentReference('InputValue'),
                    ]),
                    new Query('interfaces', null, [], [
                        new FragmentReference('TypeRef'),
                    ]),
                    new Query('enumValues', null, [], [
                        new Field('name'),
                        new Field('description'),

                        new Field('isDeprecated'),
                        new Field('deprecationReason'),
                    ]),
                    new Query('possibleTypes', null, [], [
                        new FragmentReference('TypeRef'),
                    ]),
                ]),
                new Fragment('InputValue', '__InputValue', [
                    new Field('name'),
                    new Field('description'),
                    new Query('type', null, [], [
                        new FragmentReference('TypeRef'),
                    ]),
                    new Field('defaultValue'),
                ]),
                new Fragment('TypeRef', '__Type', [
                    new Field('kind'),
                    new Field('name'),
                    new Query('ofType', null, [], [
                        new Field('kind'),
                        new Field('name'),
                        new Query('ofType', null, [], [
                            new Field('kind'),
                            new Field('name'),
                            new Query('ofType', null, [], [
                                new Field('kind'),
                                new Field('name'),
                            ]),
                        ]),
                    ]),
                ]),
            ],
            'fragmentReferences' => [
                new FragmentReference('FullType'),
                new FragmentReference('InputValue'),
                new FragmentReference('InputValue'),
                new FragmentReference('TypeRef'),
                new FragmentReference('InputValue'),
                new FragmentReference('TypeRef'),
                new FragmentReference('TypeRef'),
                new FragmentReference('TypeRef'),
            ],
            'variables'          => [],
            'variableReferences' => []
        ], $data);
    }

    public function wrongQueriesProvider()
    {
        return [
            ['{ test { id,, asd } }'],
            ['{ test { id,, } }'],
            ['{ test (a: "asd", b: <basd>) { id }'],
            ['{ test (asd: [..., asd]) { id } }'],
            ['{ test (asd: { "a": 4, "m": null, "asd": false  "b": 5, "c" : { a }}) { id } }'],
            ['asdasd'],
            ['mutation { test(asd: ... ){ ...,asd, asd } }'],
            ['mutation { test( asd: $,as ){ ...,asd, asd } }'],
            ['mutation { test{ . test on Test { id } } }'],
            ['mutation { test( a: "asdd'],
            ['mutation { test( a: { "asd": 12 12'],
            ['mutation { test( a: { "asd": 12'],
        ];
    }

    /**
     * @dataProvider mutationProvider
     */
    public function testMutations($query, $structure)
    {
        $parser = new Parser();

        $parsedStructure = $parser->parse($query);

        $this->assertEquals($parsedStructure, $structure);
    }

    public function testTypedFragment()
    {
        $parser          = new Parser();
        $parsedStructure = $parser->parse('
            {
                test: test {
                    name,
                    ... on UnionType {
                        unionName
                    }
                }
            }
        ');

        $this->assertEquals($parsedStructure, [
            'queries'            => [
                new Query('test', 'test', [],
                    [
                        new Field('name', null),
                        new TypedFragmentReference('UnionType', [
                            new Field('unionName')
                        ])
                    ])
            ],
            'mutations'          => [],
            'fragments'          => [],
            'fragmentReferences' => [],
            'variables'          => [],
            'variableReferences' => []
        ]);
    }

    public function mutationProvider()
    {
        return [
            [
                'query ($variable: Int){ query ( teas: $variable ) { alias: name } }',
                [
                    'queries'            => [
                        new Query('query', null,
                            [
                                new Argument('teas', new VariableReference('variable', (new Variable('variable', 'Int'))->setUsed(true)))
                            ],
                            [
                                new Field('name', 'alias')
                            ])
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [
                        (new Variable('variable', 'Int'))->setUsed(true)
                    ],
                    'variableReferences' => [
                        new VariableReference('variable', (new Variable('variable', 'Int'))->setUsed(true))
                    ]
                ]
            ],
            [
                '{ query { alias: name } }',
                [
                    'queries'            => [
                        new Query('query', null, [], [new Field('name', 'alias')])
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                'mutation { createUser ( email: "test@test.com", active: true ) { id } }',
                [
                    'queries'            => [],
                    'mutations'          => [
                        new Mutation(
                            'createUser',
                            null,
                            [
                                new Argument('email', new Literal('test@test.com')),
                                new Argument('active', new Literal(true)),
                            ],
                            [
                                new Field('id')
                            ]
                        )
                    ],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                'mutation { test : createUser (id: 4) }',
                [
                    'queries'            => [],
                    'mutations'          => [
                        new Mutation(
                            'createUser',
                            'test',
                            [
                                new Argument('id', new Literal(4)),
                            ],
                            []
                        )
                    ],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ]
        ];
    }

    /**
     * @dataProvider queryProvider
     */
    public function testParser($query, $structure)
    {
        $parser          = new Parser();
        $parsedStructure = $parser->parse($query);

        $this->assertEquals($structure, $parsedStructure);
    }


    public function queryProvider()
    {
        return [
            [
                '{ test (id: -5) { id } } ',
                [
                    'queries'            => [
                        new Query('test', null, [
                            new Argument('id', new Literal(-5))
                        ], [
                            new Field('id'),
                        ])
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                "{ test (id: -5) \r\n { id } } ",
                [
                    'queries'            => [
                        new Query('test', null, [
                            new Argument('id', new Literal(-5))
                        ], [
                            new Field('id'),
                        ])
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                'query CheckTypeOfLuke {
                  hero(episode: EMPIRE) {
                    __typename,
                    name
                  }
                }',
                [
                    'queries'            => [
                        new Query('hero', null, [
                            new Argument('episode', new Literal('EMPIRE'))
                        ], [
                            new Field('__typename'),
                            new Field('name'),
                        ])
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                '{ test { __typename, id } }',
                [
                    'queries'            => [
                        new Query('test', null, [], [
                            new Field('__typename'),
                            new Field('id'),
                        ])
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                '{}',
                [
                    'queries'            => [],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                'query test {}',
                [
                    'queries'            => [],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                'query {}',
                [
                    'queries'            => [],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                'mutation setName { setUserName }',
                [
                    'queries'            => [],
                    'mutations'          => [new Mutation('setUserName')],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                '{ test { ...userDataFragment } } fragment userDataFragment on User { id, name, email }',
                [
                    'queries'            => [
                        new Query('test', null, [], [new FragmentReference('userDataFragment')])
                    ],
                    'mutations'          => [],
                    'fragments'          => [
                        new Fragment('userDataFragment', 'User', [
                            new Field('id'),
                            new Field('name'),
                            new Field('email')
                        ])
                    ],
                    'fragmentReferences' => [
                        new FragmentReference('userDataFragment')
                    ],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                '{ user (id: 10, name: "max", float: 123.123 ) { id, name } }',
                [
                    'queries'            => [
                        new Query(
                            'user',
                            null,
                            [
                                new Argument('id', new Literal('10')),
                                new Argument('name', new Literal('max')),
                                new Argument('float', new Literal('123.123'))
                            ],
                            [
                                new Field('id'),
                                new Field('name')
                            ]
                        )
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                '{ allUsers : users ( id: [ 1, 2, 3] ) { id } }',
                [
                    'queries'            => [
                        new Query(
                            'users',
                            'allUsers',
                            [
                                new Argument('id', new InputList([1, 2, 3]))
                            ],
                            [
                                new Field('id')
                            ]
                        )
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                '{ allUsers : users ( id: [ 1, "2", true, null] ) { id } }',
                [
                    'queries'            => [
                        new Query(
                            'users',
                            'allUsers',
                            [
                                new Argument('id', new InputList([1, "2", true, null]))
                            ],
                            [
                                new Field('id')
                            ]
                        )
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ],
            [
                '{ allUsers : users ( object: { "a": 123, "d": "asd",  "b" : [ 1, 2, 4 ], "c": { "a" : 123, "b":  "asd" } } ) { id } }',
                [
                    'queries'            => [
                        new Query(
                            'users',
                            'allUsers',
                            [
                                new Argument('object', new InputObject([
                                    'a' => 123,
                                    'd' => 'asd',
                                    'b' => [1, 2, 4],
                                    'c' => new InputObject([
                                        'a' => 123,
                                        'b' => 'asd'
                                    ])
                                ]))
                            ],
                            [
                                new Field('id')
                            ]
                        )
                    ],
                    'mutations'          => [],
                    'fragments'          => [],
                    'fragmentReferences' => [],
                    'variables'          => [],
                    'variableReferences' => []
                ]
            ]
        ];
    }

    public function testVariablesInQuery()
    {
        $parser = new Parser();

        $data = $parser->parse('
            query StarWarsAppHomeRoute($names_0:[String]!, $query: String) {
              factions(names:$names_0, test: $query) {
                id,
                ...F2
              }
            }
            fragment F0 on Ship {
              id,
              name
            }
            fragment F1 on Faction {
              id,
              factionId
            }
            fragment F2 on Faction {
              id,
              factionId,
              name,
              _shipsDRnzJ:ships(first:10) {
                edges {
                  node {
                    id,
                    ...F0
                  },
                  cursor
                },
                pageInfo {
                  hasNextPage,
                  hasPreviousPage
                }
              },
              ...F1
            }
        ');

        $this->assertArrayNotHasKey('errors', $data);
    }

}
