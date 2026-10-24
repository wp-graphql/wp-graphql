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
 * root field selected after one, see the state the request started with. Each node is set up when
 * resolution enters its part of the query and torn down when it leaves, not around each field. It
 * is set up again if one of its fields leaves the globals pointing somewhere else, such as a block
 * that loops over other posts, so the node's later fields still see it.
 *
 * The nodes are Post, Term and User models, including subclasses that inherit their setup().
 * Reading a model's fields anywhere else (from PHP, from the resolver of a field on another node,
 * or on a model with a setup() of its own) sets the model up around that one field instead, and
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
	 * @var ?\WPGraphQL\Model\Model<mixed>
	 */
	private static $active_model;

	/**
	 * The models that own a node in the query, keyed by the node's response path.
	 *
	 * @var array<string,\WPGraphQL\Model\Model<mixed>>
	 */
	private $owners = [];

	/**
	 * The node contexts currently applied, outermost first.
	 *
	 * @var array<int,array{key:string,model:\WPGraphQL\Model\Model<mixed>,state:array<string,mixed>,previous:?\WPGraphQL\Model\Model<mixed>,applied:array{0:?int,1:?int,2:?int}}>
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

			// A model passed down as the source of a nested object (say, an "seo" object resolved
			// from the post) is already set up by the node above, so it isn't set up again.
			if ( isset( $this->owners[ $key ] ) && ( empty( $chain ) || end( $chain )[1] !== $this->owners[ $key ] ) ) {
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

		$stack_size = count( $this->stack );

		// A field of the node may have pointed the globals somewhere else, say by rendering a block
		// that loops over other posts. Set the node up again so its later fields see it.
		if ( $shared > 0 && $shared === $stack_size && self::applied_state() !== $this->stack[ $shared - 1 ]['applied'] ) {
			--$shared;
		}

		for ( $depth = $stack_size; $depth > $shared; $depth-- ) {
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
	 * @param \WPGraphQL\Model\Model<mixed> $model    The model to set up.
	 * @param callable():T                  $callback The callback to run.
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
	 * @param string                        $key   The node's response path.
	 * @param \WPGraphQL\Model\Model<mixed> $model The model that owns the node.
	 *
	 * @throws \Throwable When the model's setup() throws, after restoring the previous state.
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
			'applied'  => self::applied_state(),
		];
	}

	/**
	 * Identifies the object the global state currently points at: the global post, the global
	 * author and the main query's queried object.
	 *
	 * Compared before each field with what it was right after the active node was set up, to tell
	 * whether something has pointed the globals elsewhere since.
	 *
	 * @return array{0:?int,1:?int,2:?int}
	 */
	private static function applied_state(): array {
		global $wp_query;

		return [
			isset( $GLOBALS['post'] ) && $GLOBALS['post'] instanceof \WP_Post ? $GLOBALS['post']->ID : null,
			isset( $GLOBALS['authordata'] ) && $GLOBALS['authordata'] instanceof \WP_User ? $GLOBALS['authordata']->ID : null,
			$wp_query instanceof \WP_Query && isset( $wp_query->queried_object_id ) ? (int) $wp_query->queried_object_id : null,
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
	 * Whether a model owns a node: whether it uses the Post, Term or User setup().
	 *
	 * Those set up the current object for the node's part of the query. Subclasses that inherit
	 * them (a product modeled on Post, say) do too. Models without a setup(), such as Comment, leave
	 * the context of the node above them in place. Models with a setup() of their own are set up
	 * around each of their fields instead, as they always were, because their setup() may expect
	 * to run before each field.
	 *
	 * @param \WPGraphQL\Model\Model<mixed> $model The model.
	 */
	private static function sets_up_context( Model $model ): bool {
		$class = get_class( $model );

		if ( ! isset( self::$sets_up_context[ $class ] ) ) {
			$method                          = new \ReflectionMethod( $model, 'setup' );
			self::$sets_up_context[ $class ] = in_array( $method->getDeclaringClass()->getName(), [ Post::class, Term::class, User::class ], true );
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
