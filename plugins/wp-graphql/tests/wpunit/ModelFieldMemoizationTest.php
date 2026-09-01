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

	/**
	 * A field that resolves to a falsey value must return that same value on
	 * every read, not null on the second one.
	 *
	 * This guards a separate historical fix in the same method (the guard was
	 * once `! empty()`, which failed after a falsey value was memoized and
	 * dropped the field to null on re-read). It shipped without a test, so this
	 * locks the contract alongside the memoization fix.
	 *
	 * @see https://github.com/wp-graphql/wp-graphql/issues/4240
	 */
	public function testMemoizedFalseyValuesSurviveReRead() {
		$author_id = $this->create_max_time_user();

		wp_set_current_user( $author_id );

		$falsey = [
			'falseField'       => false,
			'zeroField'        => 0,
			'emptyStringField' => '',
			'nullField'        => null,
		];

		add_filter(
			'graphql_model_prepare_fields',
			static function ( $fields, $model_name ) use ( $falsey ) {
				if ( 'UserObject' !== $model_name ) {
					return $fields;
				}
				foreach ( $falsey as $key => $value ) {
					$fields[ $key ] = static function () use ( $value ) {
						return $value;
					};
				}
				return $fields;
			},
			10,
			2
		);

		$model = new \WPGraphQL\Model\User( get_user_by( 'id', $author_id ) );

		foreach ( $falsey as $key => $expected ) {
			// First read resolves and memoizes the falsey value; the second read
			// must return the same value rather than treating the field as unset.
			$this->assertSame( $expected, $model->$key, "First read of {$key} should be the resolved falsey value." );
			$this->assertSame( $expected, $model->$key, "Re-read of {$key} should return the memoized falsey value." );
		}
	}

	/**
	 * A field registered with a non-closure callback (a callable array, or the
	 * `[ 'callback' => ... ]` shape) still resolves through the model.
	 *
	 * __get() only invokes Closures, which is safe because wrap_fields() wraps
	 * every field definition in one and the original callback is invoked by
	 * prepare_field(). This locks that coupling from the extension-author side:
	 * if wrap_fields() ever stopped wrapping already-callable definitions, this
	 * would catch it.
	 */
	public function testNonClosureCallbackFieldsResolveAndMemoize() {
		$author_id = $this->create_max_time_user();

		wp_set_current_user( $author_id );

		add_filter(
			'graphql_model_prepare_fields',
			static function ( $fields, $model_name ) {
				if ( 'UserObject' !== $model_name ) {
					return $fields;
				}
				// Callable array definition (not a Closure).
				$fields['callableArrayField'] = [ self::class, 'resolve_callable_array_field' ];
				// The `callback` array shape prepare_field() unwraps, pointing at a
				// non-closure callable.
				$fields['callbackArrayField'] = [ 'callback' => [ self::class, 'resolve_callback_array_field' ] ];
				return $fields;
			},
			10,
			2
		);

		$model = new \WPGraphQL\Model\User( get_user_by( 'id', $author_id ) );

		$this->assertSame( 'resolved-via-callable-array', $model->callableArrayField );
		$this->assertSame( 'resolved-via-callable-array', $model->callableArrayField );
		$this->assertSame( 'resolved-via-callback-array', $model->callbackArrayField );
		$this->assertSame( 'resolved-via-callback-array', $model->callbackArrayField );
	}

	/**
	 * Zero-argument resolver for the callable-array field above.
	 */
	public static function resolve_callable_array_field(): string {
		return 'resolved-via-callable-array';
	}

	/**
	 * Zero-argument resolver for the `callback` array field above.
	 */
	public static function resolve_callback_array_field(): string {
		return 'resolved-via-callback-array';
	}
}
