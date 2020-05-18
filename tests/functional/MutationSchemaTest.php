<?php

$I = new FunctionalTester( $scenario );
$I->wantTo( 'Ensure mutations follow the Relay spec' );

// Set the content-type so we get a proper response from the API.
$I->haveHttpHeader( 'Content-Type', 'application/json' );

$query = '
{
  __schema {
    mutationType {
      fields {
        type {
          kind
          fields {
            name
            type {
              kind
              ofType {
                name
                kind
              }
            }
          }
        }
        args {
          name
          type {
            kind
            ofType {
              kind
              inputFields {
                name
                type {
                  kind
                  ofType {
                    name
                    kind
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}
';

// Query for the menu.
$I->sendPOST( 'http://wpgraphql.test/graphql', json_encode( [
	'query' => $query
] ) );

// Check response.
$I->seeResponseCodeIs( 200 );
$I->seeResponseIsJson();
$response       = $I->grabResponse();
$response_array = json_decode( $response, true );

// The query is valid and has no errors.
$I->assertArrayNotHasKey( 'errors', $response_array );

// The response is properly returning data as expected.
$I->assertArrayHasKey( 'data', $response_array );

$mutation_type_fields = ! empty( $response_array['data']['__schema']['mutationType']['fields'] ) ? $response_array['data']['__schema']['mutationType']['fields'] : null;

$I->assertNotEmpty( $mutation_type_fields );

/**
 * If the fields are a populated array
 */
if ( ! empty( $mutation_type_fields ) && is_array( $mutation_type_fields ) ) {

	/**
	 * Loop through the fields to ensure they have fields of their own
	 */
	foreach ( $mutation_type_fields as $mutation_type_field ) {

		$type = ! empty( $mutation_type_field['type'] ) ? $mutation_type_field['type'] : null;

		/**
		 * All mutations should declare a Type
		 */
		$I->assertNotEmpty( $type );

		if ( $type['kind'] === 'SCALAR' ) {
			return;
		}

		/**
		 * All types should have a "kind"
		 */
		$I->assertArrayHasKey( 'kind', $type );

		/**
		 * All rootMutations should be Object types
		 */
		$I->assertEquals( $type['kind'], 'OBJECT' );

		/**
		 * All rootMutations should have fields
		 */
		$I->assertNotEmpty( $type['fields'] );

		$I->assertTrue( array_filter( $type['fields'], function( $field ) {
			if ( 'clientMutationId' === $field['name'] ) {
				return true;
			}
			return false;
		} ));

	}
}

