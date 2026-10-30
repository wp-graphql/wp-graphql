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
 * The nodes are models whose Model::is_node_scoped() returns true: Post, Term and User, and
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
 *           and is_node_scoped(), and the graphql_model_node_context_globals filter. Extensions
 *           bind callbacks through graphql_bind_node_context().
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
	 * Globals that are never saved and restored, even if the graphql_model_node_context_globals
	 * filter adds them.
	 *
	 * The main query is restored property by property, never by replacing the object. The rest hold
	 * WordPress's own infrastructure (hooks, the database, caches, the current user, registries),
	 * which rolling back to an earlier value would break.
	 */
	private const NEVER_RESTORED = [ 'GLOBALS', 'wp_query', 'wp_the_query', 'wp', 'wpdb', 'wp_filter', 'wp_actions', 'wp_filters', 'wp_current_filter', 'wp_object_cache', 'current_user', 'wp_roles', 'wp_rewrite', 'wp_post_types', 'wp_taxonomies' ];

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
	 * Shared by every request in the process (a GraphQL request made from a resolver runs inside
	 * the outer one), which works because each setup saves the previous value and its teardown
	 * restores it.
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
	 * @throws \Throwable When a model's tear_down() throws, after every node has been torn down.
	 *
	 * @since x-release-please-version
	 */
	public static function end_request( AppContext $context ): void {
		if ( null === self::$requests || ! self::$requests->contains( $context ) ) {
			return;
		}

		$node_context = self::$requests[ $context ];
		self::$requests->detach( $context );

		try {
			$node_context->leave_all();
		} finally {
			// With no request left running, nothing can be the current node.
			if ( 0 === count( self::$requests ) ) {
				self::$active_model = null;
			}
		}
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

		// Every field at a path resolves on the same source, so recording (or forgetting) the
		// source here keeps the record current, including when another operation in a batch has
		// something different at the same path. Models the requester can't see never own a node.
		$parent_key = implode( '/', $parent_path );
		if ( $source instanceof Model && $source->is_node_scoped() && 'private' !== $source->get_visibility() ) {
			$this->owners[ $parent_key ] = $source;
		} else {
			unset( $this->owners[ $parent_key ] );
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
	 * while the field resolves, the callback sees the main query and the restored globals as they
	 * were then, so the field's node is the current object, and the state at the time it's called
	 * is restored afterwards, even if it throws.
	 *
	 * Only that saved state is put back. setup() isn't run again, so anything else a model's setup()
	 * does, such as adding filters, isn't in effect inside the callback.
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
	 * @throws \Throwable When the model's setup() throws, after restoring the previous state.
	 *
	 * @since x-release-please-version
	 */
	public static function run_as( Model $model, callable $callback ) {
		// Already set up as the current node, or nothing to set up (the model has no setup() or
		// tear_down() of its own, as for comments and menu items).
		if ( self::$active_model === $model || ! self::has_lifecycle( $model ) ) {
			return $callback();
		}

		$previous = self::$active_model;
		$state    = self::set_up( $model );

		try {
			return $callback();
		} finally {
			try {
				$model->tear_down();
			} finally {
				self::restore_global_state( $state );
				self::$active_model = $previous;
			}
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
		$previous      = self::$active_model;
		$state         = self::set_up( $model );
		$this->stack[] = [
			'key'      => $key,
			'model'    => $model,
			'state'    => $state,
			'previous' => $previous,
			'applied'  => self::applied_state(),
		];
	}

	/**
	 * Saves the global state, sets a model up as the current object, and returns the saved state.
	 *
	 * If setup() throws, there's nothing to tear down: the saved state is put back and the error
	 * rethrown.
	 *
	 * @param \WPGraphQL\Model\Model<mixed> $model The model to set up.
	 *
	 * @return array{query:?array<string,mixed>,globals:array<string,mixed>,unset:string[]} The state from before the setup.
	 *
	 * @throws \Throwable When the model's setup() throws, after restoring the previous state.
	 *
	 * @since x-release-please-version
	 */
	private static function set_up( Model $model ): array {
		$state = self::capture_global_state( $model );

		try {
			$model->setup();
		} catch ( \Throwable $e ) {
			self::restore_global_state( $state );
			throw $e;
		}

		self::$active_model = $model;

		return $state;
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
	 * @throws \Throwable When the model's tear_down() throws, after restoring the state.
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
	 * Every node is torn down even if one's tear_down() throws, and the first error is thrown once
	 * they all are.
	 *
	 * @throws \Throwable When a model's tear_down() throws.
	 *
	 * @since x-release-please-version
	 */
	private function leave_all(): void {
		$error = null;

		while ( ! empty( $this->stack ) ) {
			try {
				$this->pop();
			} catch ( \Throwable $e ) {
				$error = $error ?? $e;
			}
		}

		$this->owners = [];

		if ( null !== $error ) {
			throw $error;
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
	 * A global that is null is captured as unset, and unset again when restored, which code reading
	 * it through `global` can't tell apart. (Checking with isset() rather than array_key_exists()
	 * also avoids copying the whole $GLOBALS array on every capture.)
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
			if ( isset( $GLOBALS[ $name ] ) ) {
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
	 * because $wp_the_query and other code can hold a reference to the main query object. If code
	 * replaced $wp_query with a different object in the meantime (with query_posts(), say), the
	 * properties are written onto that object, and putting the original back (wp_reset_query())
	 * is that code's job, as it is outside GraphQL. The
	 * restore is shallow: a global, or a main query property, that holds an object gets the same
	 * object back, so changes made to that object in the meantime stay.
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
		 * The filter runs each time a model is set up, and when a callback is bound to the current
		 * node's state, so callbacks should be quick and have no side effects. Globals that hold
		 * WordPress's own infrastructure, such as $wpdb, $wp_filter and $current_user, are never
		 * saved or restored, even if they're added here.
		 *
		 * @param string[]                      $globals The names of the globals, as keys of $GLOBALS.
		 * @param \WPGraphQL\Model\Model<mixed> $model   The model about to be set up, or the current node when a callback is being bound.
		 *
		 * @hookGroup models
		 * @since x-release-please-version
		 */
		$globals = apply_filters( 'graphql_model_node_context_globals', self::GLOBALS, $model );

		if ( ! is_array( $globals ) ) {
			return self::GLOBALS;
		}

		return array_values( array_unique( array_diff( array_filter( $globals, 'is_string' ), self::NEVER_RESTORED ) ) );
	}
}
