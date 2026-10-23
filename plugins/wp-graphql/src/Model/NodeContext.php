<?php

namespace WPGraphQL\Model;

use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

/**
 * Points WordPress's global state at the node being resolved, for that node's part of the query.
 *
 * Models such as Post, Term and User have a setup() method that makes WordPress treat the modeled
 * object as the current one: it parses a query for the object on the global main query and sets
 * globals such as $post, so template tags and filters that read them (get_the_ID(), is_singular(),
 * get_queried_object(), render_block(), and so on) behave as they would on that object's page.
 *
 * That context is scoped to the node's part of the query. Before each field resolves, the context
 * of every node above it is set up, outermost first, so fields selected under a node see it as the
 * current object. This includes fields other plugins register on the node and fields of nested
 * values that are not models themselves, such as a list of blocks. Fields outside any node, like a
 * root field selected after one, see the state the request started with. Each node is set up once
 * when resolution enters its part of the query and torn down when it leaves, not around each field.
 *
 * Reading a model's fields outside of a node's part of the query (for example from PHP, or from
 * the resolver of a field on another node) sets the model up around that one field instead, and
 * restores the previous state afterwards.
 *
 * @internal Plumbing behind the model lifecycle. Models customize it through setup() and tear_down().
 */
final class NodeContext {

	/**
	 * The globals that setting up a node can change, besides the main query itself.
	 *
	 * These are the post data globals WP_Query::setup_postdata() writes, plus $authordata, which the
	 * User model sets.
	 */
	private const GLOBALS = [ 'post', 'authordata', 'id', 'currentday', 'currentmonth', 'page', 'pages', 'multipage', 'more', 'numpages' ];

	/**
	 * The node context of each request being executed, keyed by the request's AppContext.
	 *
	 * @var ?\SplObjectStorage<\WPGraphQL\AppContext,self>
	 */
	private static $requests;

	/**
	 * Whether each model class sets up a context, keyed by class name.
	 *
	 * @var array<class-string,bool>
	 */
	private static $sets_up_context = [];

	/**
	 * The model whose context is currently applied to the global state, if any.
	 *
	 * @var ?\WPGraphQL\Model\Model
	 */
	private static $active_model;

	/**
	 * The models that own a node in the query, keyed by the node's response path.
	 *
	 * @var array<string,\WPGraphQL\Model\Model>
	 */
	private $owners = [];

	/**
	 * The node contexts currently applied, outermost first.
	 *
	 * @var array<int,array{key:string,model:\WPGraphQL\Model\Model,state:array<string,mixed>,previous:?\WPGraphQL\Model\Model}>
	 */
	private $stack = [];

	/**
	 * Returns the node context for a request.
	 *
	 * @param \WPGraphQL\AppContext $context The request's AppContext.
	 */
	public static function for_request( AppContext $context ): self {
		if ( null === self::$requests ) {
			self::$requests = new \SplObjectStorage();
		}

		if ( ! self::$requests->contains( $context ) ) {
			self::$requests->attach( $context, new self() );
		}

		return self::$requests[ $context ];
	}

	/**
	 * Tears down the request's node contexts, restoring the state the request started with.
	 *
	 * @param \WPGraphQL\AppContext $context The request's AppContext.
	 */
	public static function end_request( AppContext $context ): void {
		if ( null === self::$requests || ! self::$requests->contains( $context ) ) {
			return;
		}

		self::$requests[ $context ]->leave_all();
		self::$requests->detach( $context );
	}

	/**
	 * Sets up the context of the nodes above a field, before the field resolves.
	 *
	 * @param mixed                                $source The value the field is resolved on.
	 * @param \GraphQL\Type\Definition\ResolveInfo $info   The field's resolve info.
	 */
	public function enter( $source, ResolveInfo $info ): void {
		$parent_path = array_slice( $info->path, 0, -1 );

		// A root field starts its own part of the query, so nodes recorded at its path by an
		// earlier operation in the same request (a batch) no longer apply.
		if ( empty( $parent_path ) ) {
			$this->forget_owners_under( (string) $info->path[0] );
		}

		if ( $source instanceof Model && self::sets_up_context( $source ) ) {
			$this->owners[ implode( '/', $parent_path ) ] = $source;
		}

		// The models that own the nodes above the field, outermost first.
		$chain = [];
		$key   = null;
		foreach ( $parent_path as $segment ) {
			$key = null === $key ? (string) $segment : $key . '/' . $segment;

			if ( isset( $this->owners[ $key ] ) ) {
				$chain[] = [ $key, $this->owners[ $key ] ];
			}
		}

		// Keep the contexts the field shares with the previous one, and replace the rest.
		$shared = 0;
		while (
			isset( $this->stack[ $shared ], $chain[ $shared ] ) &&
			$this->stack[ $shared ]['key'] === $chain[ $shared ][0] &&
			$this->stack[ $shared ]['model'] === $chain[ $shared ][1]
		) {
			++$shared;
		}

		while ( count( $this->stack ) > $shared ) {
			$this->pop();
		}

		$chain_length = count( $chain );
		for ( $i = $shared; $i < $chain_length; $i++ ) {
			$this->push( $chain[ $i ][0], $chain[ $i ][1] );
		}
	}

	/**
	 * Runs a callback with a model's context set up, unless it already is.
	 *
	 * Used for reading a model's fields. Within the model's part of the query its context is
	 * already applied, so the callback runs as is. Anywhere else, the model is set up around the
	 * callback and the previous state is restored afterwards, even if the callback throws.
	 *
	 * @template T
	 *
	 * @param \WPGraphQL\Model\Model $model    The model to set up.
	 * @param callable():T           $callback The callback to run.
	 *
	 * @return T
	 */
	public static function run_as( Model $model, callable $callback ) {
		if ( self::$active_model === $model ) {
			return $callback();
		}

		$state    = self::capture_global_state();
		$previous = self::$active_model;

		try {
			$model->setup();
			self::$active_model = $model;

			return $callback();
		} finally {
			$model->tear_down();
			self::restore_global_state( $state );
			self::$active_model = $previous;
		}
	}

	/**
	 * Sets up a node's context on top of the ones already applied.
	 *
	 * @param string                 $key   The node's response path.
	 * @param \WPGraphQL\Model\Model $model The model that owns the node.
	 */
	private function push( string $key, Model $model ): void {
		$state    = self::capture_global_state();
		$previous = self::$active_model;

		try {
			$model->setup();
		} catch ( \Throwable $e ) {
			self::restore_global_state( $state );
			throw $e;
		}

		self::$active_model = $model;
		$this->stack[]      = [
			'key'      => $key,
			'model'    => $model,
			'state'    => $state,
			'previous' => $previous,
		];
	}

	/**
	 * Tears down the innermost node context and restores the state from before it was set up.
	 */
	private function pop(): void {
		$entry = array_pop( $this->stack );

		if ( null === $entry ) {
			return;
		}

		try {
			$entry['model']->tear_down();
		} finally {
			self::restore_global_state( $entry['state'] );
			self::$active_model = $entry['previous'];
		}
	}

	/**
	 * Tears down every node context the request applied.
	 */
	private function leave_all(): void {
		while ( ! empty( $this->stack ) ) {
			$this->pop();
		}

		$this->owners = [];
	}

	/**
	 * Forgets the nodes recorded at or under a root field's path.
	 *
	 * @param string $root_key The root field's response key.
	 */
	private function forget_owners_under( string $root_key ): void {
		foreach ( array_keys( $this->owners ) as $key ) {
			if ( $key === $root_key || 0 === strpos( $key, $root_key . '/' ) ) {
				unset( $this->owners[ $key ] );
			}
		}
	}

	/**
	 * Whether a model sets up a context, by overriding Model::setup().
	 *
	 * Models that don't, such as Comment, leave the context of the node above them in place.
	 *
	 * @param \WPGraphQL\Model\Model $model The model.
	 */
	private static function sets_up_context( Model $model ): bool {
		$class = get_class( $model );

		if ( ! isset( self::$sets_up_context[ $class ] ) ) {
			$method                          = new \ReflectionMethod( $model, 'setup' );
			self::$sets_up_context[ $class ] = Model::class !== $method->getDeclaringClass()->getName();
		}

		return self::$sets_up_context[ $class ];
	}

	/**
	 * Captures the global state a model's setup() can change.
	 *
	 * @return array<string,mixed>
	 */
	private static function capture_global_state(): array {
		global $wp_query;

		$globals = [];
		foreach ( self::GLOBALS as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$globals[ $name ] = $GLOBALS[ $name ];
			}
		}

		return [
			// The main query's public properties: query vars, conditional flags, queried object.
			'query'   => $wp_query instanceof \WP_Query ? get_object_vars( $wp_query ) : null,
			'globals' => $globals,
		];
	}

	/**
	 * Restores the global state captured by capture_global_state().
	 *
	 * The main query's properties are written back onto the same object, rather than replacing it,
	 * because $wp_the_query and other code can hold a reference to the main query object.
	 *
	 * @param array<string,mixed> $state The state to restore.
	 */
	private static function restore_global_state( array $state ): void {
		global $wp_query;

		if ( is_array( $state['query'] ) && $wp_query instanceof \WP_Query ) {
			foreach ( $state['query'] as $property => $value ) {
				$wp_query->$property = $value;
			}
		}

		foreach ( self::GLOBALS as $name ) {
			if ( array_key_exists( $name, $state['globals'] ) ) {
				$GLOBALS[ $name ] = $state['globals'][ $name ]; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
			} else {
				unset( $GLOBALS[ $name ] );
			}
		}
	}
}
