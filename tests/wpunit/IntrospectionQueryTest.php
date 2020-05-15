<?php

class IntrospectionQueryTest extends \Codeception\TestCase\WPTestCase {

	public function setUp() {
		// before
		parent::setUp();

		// your set up methods here
		WPGraphQL::clear_schema();
	}

	public function tearDown() {
		// your tear down methods here
		WPGraphQL::clear_schema();

		// then
		parent::tearDown();
	}

	// Validate schema.
	public function testSchema() {
		try {
			$request = new \WPGraphQL\Request();
			$request->schema->assertValid();

			// Assert true upon success.
			$this->assertTrue( true );
		} catch ( \GraphQL\Error\InvariantViolation $e ) {
			// use --debug flag to view.
			codecept_debug( $e->getMessage() );

			// Fail upon throwing
			$this->assertTrue( false );
		}
	}

	// Test introspection query.
	public function testIntrospectionQuery() {
		$query = '
            query IntrospectionQuery {
                __schema {
                    queryType { name }
                    mutationType { name }
                    subscriptionType { name }
                    types {
                        ...FullType
                    }
                    directives {
                        name
                        description
                        locations
                        args {
                            ...InputValue
                        }
                    }
                }
            }
            fragment FullType on __Type {
                kind
                name
                description
                fields(includeDeprecated: true) {
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
                    enumValues(includeDeprecated: true) {
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
                            ofType {
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
                        }
                    }
                }
            }
        ';

		$results = graphql( array( 'query' => $query ) );

		$this->assertArrayNotHasKey( 'errors', $results );
	}
}
