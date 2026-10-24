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
 * The nodes are models whose Model::scopes_setup_to_node() returns true: Post, Term and User, and
 * subclasses that inherit theirs. Reading any other model's fields (or a node's fields from PHP,
 * or from the resolver of a field on another node) sets the model up around that one field instead,
 * and restores the previous state afterwards.
 *
 * Besides the main query, the state saved and restored is the post data globals and $authordata,
 * plus any globals added with the graphql_model_node_context_globals filter.
 *
 * Callbacks that run later than the field that created them (a connection's Deferred callback,
 * say) can be bound to the state at the time they were created with bind(), which extensions
 * reach through graphql_bind_node_context().
 *
 * @internal Plumbing behind the model lifecycle. Models customize it through setup(), tear_down()
 *           and scopes_setup_to_node(), and the graphql_model_node_context_globals filter.
 *           Extensions bind callbacks through graphql_bind_node_context().
 *
 * @since x-release-please-version
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
	 * Whether each model class overrides setup() or tear_down(), keyed by class name.
	 *
	 * @var array<class-string,bool>
	 */
	private static $has_lifecycle = [];

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
	 * @var array<int,array{key:string,model:\WPGraphQL\Model\Model<mixed>,state:array{query:?array<string,mixed>,globals:array<string,mixed>,unset:string[]},previous:?\WPGraphQL\Model\Model<mixed>,applied:array{0:?int,1:?int,2:?int}}>
	 */
	private $stack = [];

	/**
	 * Returns the node context for a request.
	 *
	 * @param \WPGraphQL\AppContext $context The request's AppContext.
	 *
	 * @since x-release-please-version
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
	 *
	 * @since x-release-please-version
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
	 *
	 * @since x-release-please-version
	 */
	public function enter( $source, ResolveInfo $info ): void {
		$parent_path = array_slice( $info->path, 0, -1 );

		// A root field starts its own part of the query, so nodes recorded at its path by an
		// earlier operation in the same request (a batch) no longer apply.
		if ( empty( $parent_path ) ) {
			$this->forget_owners_under( (string) $info->path[0] );
		}

		if ( $source instanceof Model && $source->scopes_setup_to_node() ) {
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
	 * Wraps a callback so that, whenever it's called, it runs with the global state as it is now.
	 *
	 * For callbacks that run later than the field that created them, such as a connection's
	 * Deferred callback, which runs after other fields (often of other nodes) have resolved. Bound
	 * while the field resolves, the callback sees that field's node as the current object, and the
	 * state at the time it's called is restored afterwards, even if it throws.
	 *
	 * @param callable $callback The callback to wrap.
	 *
	 * @return callable The wrapped callback, which takes the same arguments and returns the same value.
	 *
	 * @since x-release-please-version
	 */
	public static function bind( callable $callback ): callable {
		$model = self::$active_model;
		$state = self::capture_global_state( $model );

		return static function ( ...$args ) use ( $callback, $model, $state ) {
			$current       = self::capture_global_state( $model );
			$current_model = self::$active_model;

			self::restore_global_state( $state );
			self::$active_model = $model;

			try {
				return $callback( ...$args );
			} finally {
				self::restore_global_state( $current );
				self::$active_model = $current_model;
			}
		};
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
	 *
	 * @since x-release-please-version
	 */
	public static function run_as( Model $model, callable $callback ) {
		// Already set up as the current node, or nothing to set up (the model has no setup() or
		// tear_down() of its own, as for comments and menu items).
		if ( self::$active_model === $model || ! self::has_lifecycle( $model ) ) {
			return $callback();
		}

		$state    = self::capture_global_state( $model );
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
	 *
	 * @since x-release-please-version
	 */
	private function push( string $key, Model $model ): void {
		$state    = self::capture_global_state( $model );
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
	 *
	 * @since x-release-please-version
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
	 *
	 * @since x-release-please-version
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
	 *
	 * @since x-release-please-version
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
	 *
	 * @since x-release-please-version
	 */
	private function forget_owners_under( string $root_key ): void {
		foreach ( array_keys( $this->owners ) as $key ) {
			if ( $key === $root_key || 0 === strpos( $key, $root_key . '/' ) ) {
				unset( $this->owners[ $key ] );
			}
		}
	}

	/**
	 * Whether a model overrides setup() or tear_down(), so reading its fields has state to set up.
	 *
	 * @param \WPGraphQL\Model\Model<mixed> $model The model.
	 *
	 * @since x-release-please-version
	 */
	private static function has_lifecycle( Model $model ): bool {
		$class = get_class( $model );

		if ( ! isset( self::$has_lifecycle[ $class ] ) ) {
			$setup     = new \ReflectionMethod( $model, 'setup' );
			$tear_down = new \ReflectionMethod( $model, 'tear_down' );

			self::$has_lifecycle[ $class ] = Model::class !== $setup->getDeclaringClass()->getName() || Model::class !== $tear_down->getDeclaringClass()->getName();
		}

		return self::$has_lifecycle[ $class ];
	}

	/**
	 * Captures the global state setting up a model can change.
	 *
	 * @param ?\WPGraphQL\Model\Model<mixed> $model The model about to be set up, or whose context is being captured. Null when no model is set up.
	 *
	 * @return array{query:?array<string,mixed>,globals:array<string,mixed>,unset:string[]}
	 *
	 * @since x-release-please-version
	 */
	private static function capture_global_state( ?Model $model ): array {
		global $wp_query;

		$globals = [];
		$unset   = [];
		foreach ( self::get_globals( $model ) as $name ) {
			if ( array_key_exists( $name, $GLOBALS ) ) {
				$globals[ $name ] = $GLOBALS[ $name ];
			} else {
				$unset[] = $name;
			}
		}

		return [
			// The main query's public properties: query vars, conditional flags, queried object.
			'query'   => $wp_query instanceof \WP_Query ? get_object_vars( $wp_query ) : null,
			'globals' => $globals,
			'unset'   => $unset,
		];
	}

	/**
	 * Restores the global state captured by capture_global_state().
	 *
	 * The main query's properties are written back onto the same object, rather than replacing it,
	 * because $wp_the_query and other code can hold a reference to the main query object.
	 *
	 * @param array{query:?array<string,mixed>,globals:array<string,mixed>,unset:string[]} $state The state to restore.
	 *
	 * @since x-release-please-version
	 */
	private static function restore_global_state( array $state ): void {
		global $wp_query;

		if ( is_array( $state['query'] ) && $wp_query instanceof \WP_Query ) {
			foreach ( $state['query'] as $property => $value ) {
				$wp_query->$property = $value;
			}
		}

		foreach ( $state['globals'] as $name => $value ) {
			$GLOBALS[ $name ] = $value; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		}

		foreach ( $state['unset'] as $name ) {
			unset( $GLOBALS[ $name ] );
		}
	}

	/**
	 * Returns the names of the globals to save before setting up a model, and to restore after.
	 *
	 * @param ?\WPGraphQL\Model\Model<mixed> $model The model about to be set up, or whose context is being captured. Null when no model is set up.
	 *
	 * @return string[]
	 *
	 * @since x-release-please-version
	 */
	private static function get_globals( ?Model $model ): array {
		// With no model set up there's nothing node-specific to capture beyond the defaults.
		if ( null === $model ) {
			return self::GLOBALS;
		}

		/**
		 * Filters the globals saved before a model is set up as the current object, and restored
		 * when resolution leaves it.
		 *
		 * The main query ($wp_query) is always saved and restored, separately. By default the list
		 * is the post data globals WordPress sets up for a post ($post, $id, $authordata, $page,
		 * $pages, $multipage, $more, $numpages, $currentday and $currentmonth). Add a global that
		 * setting up the model can change, such as one a plugin sets on the `the_post` action
		 * (WooCommerce sets the global $product there) or one a custom model's setup() sets, so
		 * fields outside the model's part of the query don't see the value it left behind.
		 *
		 * @param string[]                      $globals The names of the globals, as keys of $GLOBALS.
		 * @param \WPGraphQL\Model\Model<mixed> $model   The model about to be set up.
		 *
		 * @hookGroup models
		 * @since x-release-please-version
		 */
		$globals = apply_filters( 'graphql_model_node_context_globals', self::GLOBALS, $model );

		if ( ! is_array( $globals ) ) {
			return self::GLOBALS;
		}

		// The main query is restored property by property, never by replacing the object.
		return array_values( array_unique( array_diff( array_filter( $globals, 'is_string' ), [ 'wp_query', 'wp_the_query', 'GLOBALS' ] ) ) );
	}
}
