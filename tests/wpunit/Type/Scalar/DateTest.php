<?php

namespace WPGraphQL\Test\Type\Scalar;

use WPGraphQL\Type\Scalar\Date;
use GraphQLRelay\Relay;
use WPGraphQL\Model\Post;

class DateTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
		add_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
	}

	public function tearDown(): void {
		remove_action( 'graphql_register_types', [ $this, 'register_test_fields' ] );
		parent::tearDown();
	}

	public function register_test_fields(): void {
		register_graphql_field( 'RootQuery', 'testDate', [
			'type' => 'Date',
			'resolve' => static function () {
				return '2022-10-27 12:00:00';
			},
		]);

		register_graphql_field( 'Post', 'testPublishDate', [
			'type' => 'Date',
			'resolve' => static function ( Post $post ) {
				return $post->date;
			},
		]);

		register_graphql_field( 'Post', 'testModifiedDate', [
			'type' => 'Date',
			'resolve' => static function ( Post $post ) {
				return $post->modified;
			},
		]);

		register_graphql_mutation( 'testDateMutation', [
			'inputFields' => [
				'date' => [ 'type' => 'Date' ],
			],
			'outputFields' => [
				'date' => [ 'type' => 'Date' ],
			],
			'mutateAndGetPayload' => static function ( $input ) {
				return [ 'date' => $input['date'] ];
			},
		]);
	}

	public function testSerializeValidDate() {
		$this->assertEquals( '2023-10-27', Date::serialize( '2023-10-27 10:30:00' ) );
		$this->assertEquals( '2023-01-01', Date::serialize( '2023-01-01' ) );
	}

	public function testSerializeInvalidDate() {
		$this->assertNull( Date::serialize( 'not-a-date' ) );
		$this->assertNull( Date::serialize( '0000-00-00 00:00:00' ) );
		$this->assertNull( Date::serialize( null ) );
	}

	public function testParseValueValidDate() {
		$this->assertEquals( '2023-10-27', Date::parseValue( '2023-10-27' ) );
	}

	public function testParseValueInvalidDate() {
		$this->expectException( \GraphQL\Error\Error::class );
		Date::parseValue( '2023-27-10' ); // Invalid format
	}

	public function testParseValueNotString() {
		$this->expectException( \GraphQL\Error\Error::class );
		Date::parseValue( 12345 );
	}

	public function testQueryPostDateFields() {
		$post_id = $this->factory()->post->create( [
			'post_date' => '2021-11-01 10:00:00',
			'post_modified' => '2021-12-15 14:30:00',
		] );
		$post = get_post( $post_id );

		$query = '
		query ($id: ID!) {
			post(id: $id) {
				testPublishDate
				testModifiedDate
			}
		}
		';

		$response = $this->graphql([
			'query' => $query,
			'variables' => [ 'id' => Relay::toGlobalId( 'post', $post->ID ) ],
		]);

        $post = new Post( $post );

		$this->assertEquals( Date::serialize( $post->date ), $response['data']['post']['testPublishDate'] );
		$this->assertEquals( Date::serialize( $post->modified ), $response['data']['post']['testModifiedDate'] );
	}

	public function testMutationWithValidDate() {
		$mutation = '
		mutation ($date: Date!) {
			testDateMutation(input: { date: $date }) {
				date
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'date' => '2024-01-20' ],
		]);

		$this->assertEquals( '2024-01-20', $response['data']['testDateMutation']['date'] );
	}

	public function testMutationWithInvalidDateFormat() {
		$mutation = '
		mutation ($date: Date!) {
			testDateMutation(input: { date: $date }) {
				date
			}
		}
		';

		$response = $this->graphql([
			'query' => $mutation,
			'variables' => [ 'date' => '20-01-2024' ],
		]);

		$this->assertArrayHasKey( 'errors', $response );
	}
}