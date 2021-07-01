<?php

namespace WPGraphQL\Data\Connection;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Post;
use WPGraphQL\Utils\Utils;

/**
 * Class PostObjectConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 */
class PostObjectConnectionResolver extends AbstractConnectionResolver {

	/**
	 * The name of the post type, or array of post types the connection resolver is resolving for
	 *
	 * @var mixed string|array
	 */
	protected $post_type;

	/**
	 * PostObjectConnectionResolver constructor.
	 *
	 * @param mixed              $source    source passed down from the resolve tree
	 * @param array              $args      array of arguments input in the field as part of the
	 *                                      GraphQL query
	 * @param AppContext         $context   Object containing app context that gets passed down the
	 *                                      resolve tree
	 * @param ResolveInfo        $info      Info about fields passed down the resolve tree
	 * @param mixed|string|array $post_type The post type to resolve for
	 *
	 * @throws Exception
	 */
	public function __construct( $source, array $args, AppContext $context, ResolveInfo $info, $post_type = 'any' ) {

		/**
		 * The $post_type can either be a single value or an array of post_types to
		 * pass to WP_Query.
		 *
		 * If the value is revision or attachment, we will leave the value
		 * as a string, as we validate against this later.
		 *
		 * If the value is anything else, we cast as an array. For example
		 *
		 * $post_type = 'post' would become [ 'post ' ], as we check later
		 * for `in_array()` if the $post_type is not "attachment" or "revision"
		 */
		if ( 'revision' === $post_type || 'attachment' === $post_type ) {
			$this->post_type = $post_type;
		} elseif ( 'any' === $post_type ) {
			$post_types      = get_post_types( [ 'show_in_graphql' => true ] );
			$this->post_type = ! empty( $post_types ) ? array_values( $post_types ) : [];
		} else {
			$post_type = is_array( $post_type ) ? $post_type : [ $post_type ];
			unset( $post_type['attachment'] );
			unset( $post_type['revision'] );
			$this->post_type = $post_type;
		}

		/**
		 * Call the parent construct to setup class data
		 */
		parent::__construct( $source, $args, $context, $info );

	}

	/**
	 * Return the name of the loader
	 *
	 * @return string
	 */
	public function get_loader_name() {
		return 'post';
	}

	/**
	 * Returns the query being executed
	 *
	 * @return \WP_Query
	 *
	 * @throws Exception
	 */
	public function get_query() {
		// Get query class.
		$queryClass = ! empty( $this->context->queryClass )
			? $this->context->queryClass
			: '\WP_Query';

		$query = new $queryClass( $this->query_args );

		if ( isset( $query->query_vars['suppress_filters'] ) && true === $query->query_vars['suppress_filters'] ) {
			throw new InvariantViolation( __( 'WP_Query has been modified by a plugin or theme to suppress_filters, which will cause issues with WPGraphQL Execution. If you need to suppress filters for a specific reason within GraphQL, consider registering a custom field to the WPGraphQL Schema with a custom resolver.', 'wp-graphql' ) );
		}

		return $query;
	}

	/**
	 * Return an array of items from the query
	 *
	 * @return array
	 */
	public function get_ids() {
		return ! empty( $this->query->posts ) ? $this->query->posts : [];
	}

	/**
	 * Determine whether the Query should execute. If it's determined that the query should
	 * not be run based on context such as, but not limited to, who the user is, where in the
	 * ResolveTree the Query is, the relation to the node the Query is connected to, etc
	 *
	 * Return false to prevent the query from executing.
	 *
	 * @return bool
	 */
	public function should_execute() {

		if ( false === $this->should_execute ) {
			return false;
		}

		/**
		 * For revisions, we only want to execute the connection query if the user
		 * has access to edit the parent post.
		 *
		 * If the user doesn't have permission to edit the parent post, then we shouldn't
		 * even execute the connection
		 */
		if ( isset( $this->post_type ) && 'revision' === $this->post_type ) {

			if ( $this->source instanceof Post ) {
				$parent_post_type_obj = get_post_type_object( $this->source->post_type );
				if ( ! isset( $parent_post_type_obj->cap->edit_post ) || ! current_user_can( $parent_post_type_obj->cap->edit_post, $this->source->ID ) ) {
					$this->should_execute = false;
				}
				/**
				 * If the connection is from the RootQuery, check if the user
				 * has the 'edit_posts' capability
				 */
			} else {
				if ( ! current_user_can( 'edit_posts' ) ) {
					$this->should_execute = false;
				}
			}
		}

		return $this->should_execute;
	}

	/**
	 * Here, we map the args from the input, then we make sure that we're only querying
	 * for IDs. The IDs are then passed down the resolve tree, and deferred resolvers
	 * handle batch resolution of the posts.
	 *
	 * @return array
	 */
	public function get_query_args() {
		/**
		 * Prepare for later use
		 */
		$last  = ! empty( $this->args['last'] ) ? $this->args['last'] : null;
		$first = ! empty( $this->args['first'] ) ? $this->args['first'] : null;

		$query_args = [];
		/**
		 * Ignore sticky posts by default
		 */
		$query_args['ignore_sticky_posts'] = true;

		/**
		 * Set the post_type for the query based on the type of post being queried
		 */
		$query_args['post_type'] = ! empty( $this->post_type ) ? $this->post_type : 'post';

		/**
		 * Don't calculate the total rows, it's not needed and can be expensive
		 */
		$query_args['no_found_rows'] = true;

		/**
		 * Set the post_status to "publish" by default
		 */
		$query_args['post_status'] = 'publish';

		/**
		 * Set posts_per_page the highest value of $first and $last, with a (filterable) max of 100
		 */
		$query_args['posts_per_page'] = min( max( absint( $first ), absint( $last ), 10 ), $this->query_amount ) + 1;

		/**
		 * Set the graphql_cursor_offset which is used by Config::graphql_wp_query_cursor_pagination_support
		 * to filter the WP_Query to support cursor pagination
		 */
		$cursor_offset = $this->get_offset();

		$query_args['graphql_cursor_offset']  = $cursor_offset;
		$query_args['graphql_cursor_compare'] = ( ! empty( $last ) ) ? '>' : '<';

		$query_args['graphql_after_cursor']  = ! empty( $this->get_after_offset() ) ? $this->get_after_offset() : null;
		$query_args['graphql_before_cursor'] = ! empty( $this->get_before_offset() ) ? $this->get_before_offset() : null;

		/**
		 * If the starting offset is not 0 sticky posts will not be queried as the automatic checks in wp-query don't
		 * trigger due to the page parameter not being set in the query_vars, fixes #732
		 */
		if ( 0 !== $cursor_offset ) {
			$query_args['ignore_sticky_posts'] = true;
		}

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $this->args;

		/**
		 * Collect the input_fields and sanitize them to prepare them for sending to the WP_Query
		 */
		$input_fields = [];
		if ( ! empty( $this->args['where'] ) ) {
			$input_fields = $this->sanitize_input_fields( $this->args['where'] );
		}

		/**
		 * If the post_type is "attachment" set the default "post_status" $query_arg to "inherit"
		 */
		if ( 'attachment' === $this->post_type || 'revision' === $this->post_type ) {
			$query_args['post_status'] = 'inherit';

			if ( isset( $query_args['post_parent'] ) ) {

				/**
				 * Unset the "post_parent" for attachments, as we don't really care if they
				 * have a post_parent set by default
				 */
				unset( $query_args['post_parent'] );

			}
		}

		/**
		 * Merge the input_fields with the default query_args
		 */
		if ( ! empty( $input_fields ) ) {
			$query_args = array_merge( $query_args, $input_fields );
		}

		/**
		 * If the query is a search, the source is not another Post, and the parent input $arg is not
		 * explicitly set in the query, unset the $query_args['post_parent'] so the search
		 * can search all posts, not just top level posts.
		 */
		if ( ! $this->source instanceof \WP_Post && isset( $query_args['search'] ) && ! isset( $input_fields['parent'] ) ) {
			unset( $query_args['post_parent'] );
		}

		/**
		 * If the query contains search default the results to
		 */
		if ( isset( $query_args['search'] ) && ! empty( $query_args['search'] ) ) {
			/**
			 * Don't order search results by title (causes funky issues with cursors)
			 */
			$query_args['search_orderby_title'] = false;
			$query_args['orderby']              = 'date';
			$query_args['order']                = isset( $last ) ? 'ASC' : 'DESC';
		}

		if ( empty( $this->args['where']['orderby'] ) ) {
			if ( ! empty( $query_args['post__in'] ) ) {

				$post_in = $query_args['post__in'];
				// Make sure the IDs are integers
				$post_in = array_map( function ( $id ) {
					return absint( $id );
				}, $post_in );

				// If we're coming backwards, let's reverse the IDs
				if ( ! empty( $this->args['last'] ) || ! empty( $this->args['before'] ) ) {
					$post_in = array_reverse( $post_in );
				}

				if ( ! empty( $this->get_offset() ) ) {
					// Determine if the offset is in the array
					$key = array_search( $this->get_offset(), $post_in, true );

					// If the offset is in the array
					if ( false !== $key ) {
						$key     = absint( $key );
						$post_in = array_slice( $post_in, $key + 1, null, true );
					}
				}

				$query_args['post__in'] = $post_in;
				$query_args['orderby']  = 'post__in';
				$query_args['order']    = isset( $last ) ? 'ASC' : 'DESC';
			}
		}

		/**
		 * Map the orderby inputArgs to the WP_Query
		 */
		if ( isset( $this->args['where']['orderby'] ) && is_array( $this->args['where']['orderby'] ) ) {
			$query_args['orderby'] = [];
			foreach ( $this->args['where']['orderby'] as $orderby_input ) {
				/**
				 * These orderby options should not include the order parameter.
				 */
				if ( in_array(
					$orderby_input['field'],
					[
						'post__in',
						'post_name__in',
						'post_parent__in',
					],
					true
				) ) {
					$query_args['orderby'] = esc_sql( $orderby_input['field'] );
				} elseif ( ! empty( $orderby_input['field'] ) ) {

					$order = $orderby_input['order'];

					if ( isset( $query_args['graphql_args']['last'] ) && ! empty( $query_args['graphql_args']['last'] ) ) {
						if ( 'ASC' === $order ) {
							$order = 'DESC';
						} else {
							$order = 'ASC';
						}
					}

					$query_args['orderby'][ esc_sql( $orderby_input['field'] ) ] = esc_sql( $order );
				}
			}
		}

		/**
		 * Convert meta_value_num to seperate meta_value value field which our
		 * graphql_wp_term_query_cursor_pagination_support knowns how to handle
		 */
		if ( isset( $query_args['orderby'] ) && 'meta_value_num' === $query_args['orderby'] ) {
			$query_args['orderby'] = [
				'meta_value' => empty( $query_args['order'] ) ? 'DESC' : $query_args['order'],
			];
			unset( $query_args['order'] );
			$query_args['meta_type'] = 'NUMERIC';
		}

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( empty( $query_args['orderby'] ) ) {
			$query_args['order'] = ! empty( $last ) ? 'ASC' : 'DESC';
		}

		/**
		 * NOTE: Only IDs should be queried here as the Deferred resolution will handle
		 * fetching the full objects, either from cache of from a follow-up query to the DB
		 */
		$query_args['fields'] = 'ids';

		/**
		 * Filter the $query args to allow folks to customize queries programmatically
		 *
		 * @param array       $query_args The args that will be passed to the WP_Query
		 * @param mixed       $source     The source that's passed down the GraphQL queries
		 * @param array       $args       The inputArgs on the field
		 * @param AppContext  $context    The AppContext passed down the GraphQL tree
		 * @param ResolveInfo $info       The ResolveInfo passed down the GraphQL tree
		 */
		$query_args = apply_filters( 'graphql_post_object_connection_query_args', $query_args, $this->source, $this->args, $this->context, $this->info );

		/**
		 * Return the $query_args
		 */
		return $query_args;
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to WP_Query
	 * friendly keys. There's probably a cleaner/more dynamic way to approach this, but
	 * this was quick. I'd be down to explore more dynamic ways to map this, but for
	 * now this gets the job done.
	 *
	 * @param array $where_args The args passed to the connection
	 *
	 * @return array
	 * @since  0.0.5
	 */
	public function sanitize_input_fields( array $where_args ) {

		$arg_mapping = [
			'authorName'    => 'author_name',
			'authorIn'      => 'author__in',
			'authorNotIn'   => 'author__not_in',
			'categoryId'    => 'cat',
			'categoryName'  => 'category_name',
			'categoryIn'    => 'category__in',
			'categoryNotIn' => 'category__not_in',
			'tagId'         => 'tag_id',
			'tagIds'        => 'tag__and',
			'tagIn'         => 'tag__in',
			'tagNotIn'      => 'tag__not_in',
			'tagSlugAnd'    => 'tag_slug__and',
			'tagSlugIn'     => 'tag_slug__in',
			'search'        => 's',
			'id'            => 'p',
			'parent'        => 'post_parent',
			'parentIn'      => 'post_parent__in',
			'parentNotIn'   => 'post_parent__not_in',
			'in'            => 'post__in',
			'notIn'         => 'post__not_in',
			'nameIn'        => 'post_name__in',
			'hasPassword'   => 'has_password',
			'password'      => 'post_password',
			'status'        => 'post_status',
			'stati'         => 'post_status',
			'dateQuery'     => 'date_query',
			'contentTypes'  => 'post_type',
		];

		/**
		 * Map and sanitize the input args to the WP_Query compatible args
		 */
		$query_args = Utils::map_input( $where_args, $arg_mapping );

		if ( ! empty( $query_args['post_status'] ) ) {
			$allowed_stati             = $this->sanitize_post_stati( $query_args['post_status'] );
			$query_args['post_status'] = ! empty( $allowed_stati ) ? $allowed_stati : [ 'publish' ];
		}

		/**
		 * Filter the input fields
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the WP_Query
		 *
		 * @param array              $query_args The mapped query arguments
		 * @param array              $args       Query "where" args
		 * @param mixed              $source     The query results for a query calling this
		 * @param array              $all_args   All of the arguments for the query (not just the "where" args)
		 * @param AppContext         $context    The AppContext object
		 * @param ResolveInfo        $info       The ResolveInfo object
		 * @param mixed|string|array $post_type  The post type for the query
		 *
		 * @return array
		 * @since 0.0.5
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_query', $query_args, $where_args, $this->source, $this->args, $this->context, $this->info, $this->post_type );

		/**
		 * Return the Query Args
		 */
		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];

	}

	/**
	 * Limit the status of posts a user can query.
	 *
	 * By default, published posts are public, and other statuses require permission to access.
	 *
	 * This strips the status from the query_args if the user doesn't have permission to query for
	 * posts of that status.
	 *
	 * @param mixed $stati The status(es) to sanitize
	 *
	 * @return array|null
	 */
	public function sanitize_post_stati( $stati ) {

		/**
		 * If no stati is explicitly set by the input, default to publish. This will be the
		 * most common scenario.
		 */
		if ( empty( $stati ) ) {
			$stati = [ 'publish' ];
		}

		/**
		 * Parse the list of stati
		 */
		$statuses = wp_parse_slug_list( $stati );

		/**
		 * Get the Post Type object
		 */
		$post_type_objects = [];
		if ( is_array( $this->post_type ) ) {
			foreach ( $this->post_type as $post_type ) {
				$post_type_objects[] = get_post_type_object( $post_type );
			}
		} else {
			$post_type_objects[] = get_post_type_object( $this->post_type );
		}

		/**
		 * Make sure the statuses are allowed to be queried by the current user. If so, allow it,
		 * otherwise return null, effectively removing it from the $allowed_statuses that will
		 * be passed to WP_Query
		 */
		$allowed_statuses = array_filter(
			array_map(
				function ( $status ) use ( $post_type_objects ) {
					foreach ( $post_type_objects as $post_type_object ) {
						if ( 'publish' === $status ) {
							return $status;
						}

						if ( 'private' === $status && ( ! isset( $post_type_object->cap->read_private_posts ) || ! current_user_can( $post_type_object->cap->read_private_posts ) ) ) {
							return null;
						}

						if ( ! isset( $post_type_object->cap->edit_posts ) || ! current_user_can( $post_type_object->cap->edit_posts ) ) {
							return null;
						}

						return $status;
					}
				},
				$statuses
			)
		);

		/**
		 * If there are no allowed statuses to pass to WP_Query, prevent the connection
		 * from executing
		 *
		 * For example, if a subscriber tries to query:
		 *
		 * {
		 *   posts( where: { stati: [ DRAFT ] } ) {
		 *     ...fields
		 *   }
		 * }
		 *
		 * We can safely prevent the execution of the query because they are asking for content
		 * in a status that we know they can't ask for.
		 */
		if ( empty( $allowed_statuses ) ) {
			$this->should_execute = false;
		}

		/**
		 * Return the $allowed_statuses to the query args
		 */
		return $allowed_statuses;
	}

	/**
	 * Determine whether or not the the offset is valid, i.e the post corresponding to the offset
	 * exists. Offset is equivalent to post_id. So this function is equivalent to checking if the
	 * post with the given ID exists.
	 *
	 * @param int $offset The ID of the node used in the cursor offset
	 *
	 * @return bool
	 */
	public function is_valid_offset( $offset ) {
		return ! empty( get_post( absint( $offset ) ) );
	}

}
