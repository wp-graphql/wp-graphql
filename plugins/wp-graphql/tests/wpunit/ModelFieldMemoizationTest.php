<?php

/**
 * Regression tests for Model::__get() memoization.
 *
 * Field values resolve through closures and are memoized onto the model
 * instance. A memoized value must be treated as data on later reads, even
 * when the value is a string that collides with the name of a defined PHP
 * function ("Max" vs max(), "Time" vs time(), ...). Before the fix,
 * re-reading such a field ran is_callable() on the raw string, which matches
 * function names case-insensitively and invoked the builtin with no
 * arguments, causing a fatal.
 *
 * A query triggers the re-read whenever the same field on the same model
 * instance resolves more than once, e.g. requesting a field twice through an
 * alias.
 *
 * @see https://github.com/wp-graphql/wp-graphql/issues/4240
 */
class ModelFieldMemoizationTest extends \Tests\WPGraphQL\TestCase\WPGraphQLTestCase {

	/**
	 * Clear the cached schema before each test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->clearSchema();
	}

	/**
	 * Clear the cached schema after each test.
	 */
	public function tearDown(): void {
		$this->clearSchema();
		parent::tearDown();
	}

	/**
	 * Create an author whose name parts collide with PHP builtins (max(), time()).
	 */
	private function create_max_time_user(): int {
		return self::factory()->user->create(
			[
				'role'       => 'author',
				'first_name' => 'Max',
				'last_name'  => 'Time',
			]
		);
	}

	/**
	 * Widest surface: a GraphQL query that resolves the same field twice on one
	 * model instance via an alias, where the value collides with a PHP builtin
	 * function name. The aliased (second) read returns the memoized string.
	 */
	public function testAliasedFieldReReadResolvesMemoizedValueAsData() {
		$author_id = $this->create_max_time_user();

		wp_set_current_user( $author_id );

		$query = '
		{
			viewer {
				firstName
				firstNameAgain: firstName
				lastName
				lastNameAgain: lastName
			}
		}
		';

		$actual = $this->graphql( [ 'query' => $query ] );

		$this->assertArrayNotHasKey( 'errors', $actual );
		$this->assertSame( 'Max', $actual['data']['viewer']['firstName'] );
		$this->assertSame( 'Max', $actual['data']['viewer']['firstNameAgain'] );
		$this->assertSame( 'Time', $actual['data']['viewer']['lastName'] );
		$this->assertSame( 'Time', $actual['data']['viewer']['lastNameAgain'] );
	}

	/**
	 * Precise surface: reading the same model property twice returns the
	 * memoized value instead of invoking the PHP builtin it collides with.
	 */
	public function testModelReReadReturnsMemoizedStringValue() {
		$author_id = $this->create_max_time_user();

		wp_set_current_user( $author_id );

		$model = new \WPGraphQL\Model\User( get_user_by( 'id', $author_id ) );

		$this->assertSame( 'Max', $model->firstName );
		$this->assertSame( 'Max', $model->firstName );
		$this->assertSame( 'Time', $model->lastName );
		$this->assertSame( 'Time', $model->lastName );
	}
}
