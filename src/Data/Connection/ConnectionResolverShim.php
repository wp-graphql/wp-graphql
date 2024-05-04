<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Class ConnectionResolverShim - Shim for ConnectionResolver 2.0
 *
 * Note: This is NOT a true shim, but rather illustrates the changes that need to be made to convert classes to use the new ConnectionResolver.
 *
 * Issues like changes in method signatures (visibility, arg/return types, etc) are not addressed here.
 *
 * @package WPGraphQL\Data\Connection
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<mixed[]|object|mixed>
 */
abstract class ConnectionResolverShim extends AbstractConnectionResolver {

	/**
	 * Returns the source of the connection
	 *
	 * @deprecated @todo
	 * @return mixed
	 *
	 * @codeCoverageIgnore
	 */
	public function getSource() {
		_deprecated_function( __METHOD__, '@todo', parent::class . '::get_source()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $this->get_source();
	}

	/**
	 * Get the loader name
	 *
	 * @deprecated @todo
	 *
	 * @return \WPGraphQL\Data\Loader\AbstractDataLoader
	 *
	 * @codeCoverageIgnore
	 */
	protected function getLoader() {
		_deprecated_function( __METHOD__, '@todo', parent::class . '::get_loader()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		return $this->get_loader();
	}

	/**
	 * Returns the $args passed to the connection
	 *
	 * @deprecated @todo
	 *
	 * @return array<string,mixed>
	 *
	 * @codeCoverageIgnore
	 */
	public function getArgs(): array {
		_deprecated_function( __METHOD__, '1.11.0', static::class . '::get_args()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $this->get_args();
	}

	/**
	 * Returns the AppContext of the connection
	 *
	 * @deprecated @todo
	 *
	 * @codeCoverageIgnore
	 */
	public function getContext(): AppContext {
		_deprecated_function( __METHOD__, '@todo', parent::class . '::get_context()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $this->get_context();
	}

	/**
	 * Returns the ResolveInfo of the connection
	 *
	 * @deprecated @todo
	 *
	 * @codeCoverageIgnore
	 */
	public function getInfo(): ResolveInfo {
		_deprecated_function( __METHOD__, '@todo', parent::class . '::get_info()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $this->get_info();
	}

	/**
	 * Returns whether the connection should execute
	 *
	 * @deprecated @todo
	 *
	 * @codeCoverageIgnore
	 */
	public function getShouldExecute(): bool {
		_deprecated_function( __METHOD__, '@todo', parent::class . '::get_should_execute()' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		return $this->get_should_execute();
	}

	/**
	 * @param string $key   The key of the query arg to set
	 * @param mixed  $value The value of the query arg to set
	 *
	 * @return static
	 *
	 * @deprecated 0.3.0
	 *
	 * @codeCoverageIgnore
	 */
	public function setQueryArg( $key, $value ) {
		_deprecated_function( __METHOD__, '0.3.0', static::class . '::set_query_arg()' );

		return $this->set_query_arg( $key, $value );
	}

	/**
	 * {@inheritDoc}
	 *
	 * Method `get_loader_name()` is no longer abstract.
	 *
	 * Overloading should be done via `loader_name()`.
	 *
	 * @throws \Exception
	 */
	protected function loader_name(): string {
		throw new Exception(
			sprintf(
				// translators: %s is the name of the connection resolver class.
				esc_html__( 'Class %s does not implement a valid method `loader_name()`.', 'wp-graphql' ),
				static::class
			)
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * Method `get_query_args()` is no longer abstract.
	 *
	 * Overloading should be done via `prepare_query_args()`.
	 *
	 * @throws \Exception
	 */
	protected function prepare_query_args( array $args ): array {
		throw new Exception(
			sprintf(
				// translators: %s is the name of the connection resolver class.
				esc_html__( 'Class %s does not implement a valid method `prepare_query_args()`.', 'wp-graphql' ),
				static::class
			)
		);
	}

	/**
	 * Method `get_query()` is no longer abstract. Overloading (if necesary) should be done via `query()` and `query_class()`.
	 */

	/**
	 * Method `should_execute()` is now protected and no longer abstract. It defaults to `true`.
	 *
	 * Overload `should_execute()` or `pre_should_execute()` should only occur if there is a reason the connecton should not always execute..
	 */

	/**
	 * This method is now abstract.
	 *
	 * {@inheritDoc}
	 *
	 * @throws \Exception
	 */
	public function get_ids_from_query(): array {
		throw new Exception(
			sprintf(
				// translators: %s is the name of the connection resolver class.
				esc_html__( 'Class %s does not implement a valid method `get_ids_from_query()`.', 'wp-graphql' ),
				static::class
			)
		);
	}

	/**
	 * This returns the offset to be used in the $query_args based on the $args passed to the
	 * GraphQL query.
	 *
	 * @deprecated 1.9.0
	 *
	 * @codeCoverageIgnore
	 *
	 * @return int|mixed
	 */
	public function get_offset() {
		_deprecated_function( __METHOD__, '1.9.0', static::class . '::get_offset_for_cursor()' );

		$args = $this->get_args();

		// Using shorthand since this is for deprecated code.
		$cursor = $args['after'] ?? null;
		$cursor = $cursor ?: ( $args['before'] ?? null );

		return $this->get_offset_for_cursor( $cursor );
	}

	/**
	 * Method get_nodes() should now be overloaded `prepare_nodes()`.
	 */

	/**
	 * Method `get_edges()` should be overloaded `prepare_edges()`.
	 */

	/**
	 * Method `get_page_info()` should now be overloaded via `prepare_page_info()`.
	 */
}
