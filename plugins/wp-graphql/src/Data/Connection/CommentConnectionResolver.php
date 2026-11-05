<?php

namespace WPGraphQL\Data\Connection;

use GraphQL\Error\UserError;
use WPGraphQL\Model\Comment;
use WPGraphQL\Model\Post;
use WPGraphQL\Utils\Utils;

/**
 * Class CommentConnectionResolver
 *
 * @package WPGraphQL\Data\Connection
 * @extends \WPGraphQL\Data\Connection\AbstractConnectionResolver<\WP_Comment_Query>
 */
class CommentConnectionResolver extends AbstractConnectionResolver {

	/**
	 * {@inheritDoc}
	 *
	 * @throws \GraphQL\Error\UserError
	 */
	protected function prepare_query_args( array $args ): array {
		/**
		 * Prepare for later use
		 */
		$last = ! empty( $args['last'] ) ? $args['last'] : null;

		$query_args = [];

		/**
		 * Don't calculate the total rows, it's not needed and can be expensive
		 */
		$query_args['no_found_rows'] = true;

		/**
		 * Set the default comment_status for Comment Queries to be "comment_approved"
		 */
		$query_args['status'] = 'approve';

		/**
		 * Set the number, ensuring it doesn't exceed the amount set as the $max_query_amount
		 *
		 * @since 0.0.6
		 */
		$query_args['number'] = $this->get_query_amount() + 1;

		/**
		 * Set the default order
		 */
		$query_args['orderby'] = 'comment_date';

		/**
		 * Take any of the $args that were part of the GraphQL query and map their
		 * GraphQL names to the WP_Term_Query names to be used in the WP_Term_Query
		 *
		 * @since 0.0.5
		 */
		$input_fields = [];
		if ( ! empty( $args['where'] ) ) {
			$input_fields = $this->sanitize_input_fields( $args['where'] );
		}

		/**
		 * Merge the default $query_args with the $args that were entered
		 * in the query.
		 *
		 * @since 0.0.5
		 */
		if ( ! empty( $input_fields ) ) {
			$query_args = array_merge( $query_args, $input_fields );
		}

		/**
		 * Limit what users who can't moderate comments can filter, search and sort by.
		 */
		if ( ! current_user_can( 'moderate_comments' ) ) {
			$query_args = $this->restrict_query_args_for_non_moderators( $query_args );
		}

		/**
		 * Throw an exception if the query is attempted to be queried by
		 */
		if ( 'comment__in' === $query_args['orderby'] && empty( $query_args['comment__in'] ) ) {
			throw new UserError( esc_html__( 'In order to sort by comment__in, an array of IDs must be passed as the commentIn argument', 'wp-graphql' ) );
		}

		/**
		 * If there's no orderby params in the inputArgs, set order based on the first/last argument
		 */
		if ( empty( $query_args['order'] ) ) {
			$query_args['order'] = ! empty( $last ) ? 'ASC' : 'DESC';
		}

		/**
		 * Set the graphql_cursor_compare to determine
		 * whether the data is being paginated forward (>) or backward (<)
		 * default to forward
		 */
		$query_args['graphql_cursor_compare'] = ( isset( $last ) ) ? '>' : '<';

		// these args are used by the cursor builder to generate the proper SQL needed to respect the cursors.
		// A cursor on a comment the user can't see is ignored, like a cursor on a comment that doesn't exist.
		$after_offset  = $this->get_after_offset();
		$before_offset = $this->get_before_offset();

		$query_args['graphql_after_cursor']  = ! empty( $after_offset ) && $this->is_valid_offset( (int) $after_offset ) ? $after_offset : null;
		$query_args['graphql_before_cursor'] = ! empty( $before_offset ) && $this->is_valid_offset( (int) $before_offset ) ? $before_offset : null;

		/**
		 * Pass the graphql $args to the WP_Query
		 */
		$query_args['graphql_args'] = $args;

		// encode the graphql args as a cache domain to ensure the
		// graphql_args are used to identify different queries.
		// see: https://core.trac.wordpress.org/ticket/35075
		$encoded_args               = wp_json_encode( $args );
		$query_args['cache_domain'] = ! empty( $encoded_args ) ? 'graphql:' . md5( $encoded_args ) : 'graphql';

		/**
		 * We only want to query IDs because deferred resolution will resolve the full
		 * objects.
		 */
		$query_args['fields'] = 'ids';

		/**
		 * Filters the query args used by the connection.
		 *
		 * @param array<string,mixed>                  $query_args array of query_args being passed to the
		 * @param mixed                                $source     source passed down from the resolve tree
		 * @param array<string,mixed>                  $args       array of arguments input in the field as part of the GraphQL query
		 * @param \WPGraphQL\AppContext                $context object passed down the resolve tree
		 * @param \GraphQL\Type\Definition\ResolveInfo $info info about fields passed down the resolve tree
		 *
		 * @hookGroup connections
		 * @since 0.0.6
		 */
		return apply_filters( 'graphql_comment_connection_query_args', $query_args, $this->source, $args, $this->context, $this->info );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function query_class(): string {
		return \WP_Comment_Query::class;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function loader_name(): string {
		return 'comment';
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_ids_from_query() {
		/**
		 * @todo This is for b/c. We can just use $this->get_query().
		 */
		$queried  = isset( $this->query ) ? $this->query : $this->get_query();
		$comments = $queried->get_comments();

		/** @var array<int<0, max>> $ids */
		$ids = ! empty( $comments ) ? $comments : [];

		// If we're going backwards, we need to reverse the array.
		$args = $this->get_args();

		if ( ! empty( $args['last'] ) ) {
			$ids = array_reverse( $ids );
		}

		return $ids;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function prepare_args( array $args ): array {
		if ( ! empty( $args['where'] ) ) {
			// Ensure all IDs are converted to database IDs.
			foreach ( $args['where'] as $input_key => $input_value ) {
				if ( empty( $input_value ) ) {
					continue;
				}

				switch ( $input_key ) {
					case 'authorIn':
					case 'authorNotIn':
					case 'commentIn':
					case 'commentNotIn':
					case 'parentIn':
					case 'parentNotIn':
					case 'contentAuthorIn':
					case 'contentAuthorNotIn':
					case 'contentId':
					case 'contentIdIn':
					case 'contentIdNotIn':
					case 'contentAuthor':
					case 'userId':
						if ( is_array( $input_value ) ) {
							$args['where'][ $input_key ] = array_map(
								static function ( $id ) {
									return Utils::get_database_id_from_id( $id );
								},
								$input_value
							);
							break;
						}
						$args['where'][ $input_key ] = Utils::get_database_id_from_id( $input_value );
						break;
					case 'includeUnapproved':
						if ( is_string( $input_value ) ) {
							$input_value = [ $input_value ];
						}
						$args['where'][ $input_key ] = array_map(
							static function ( $id ) {
								if ( is_email( $id ) ) {
									return $id;
								}

								return Utils::get_database_id_from_id( $id );
							},
							$input_value
						);
						break;
				}
			}
		}

		/**
		 * Filters the GraphQL args before they are used in get_query_args().
		 *
		 * @param array<string,mixed> $args     The GraphQL args passed to the resolver.
		 * @param self                $resolver Instance of the ConnectionResolver
		 *
		 * @hookGroup connections
		 * @since 1.11.0
		 */
		return apply_filters( 'graphql_comment_connection_args', $args, $this );
	}

	/**
	 * Limits what a user who can't moderate comments can filter, search and sort comments by.
	 *
	 * Each rule keeps the query to comments, and to fields, the user can see. A filter on something only
	 * moderators can see matches nothing, rather than erroring, and when nothing the user can see is left
	 * the query isn't run. So results and pagination info only reflect comments the user can see.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args after the where args are merged in.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_query_args_for_non_moderators( array $query_args ): array {
		$query_args = $this->restrict_author_email( $query_args );
		$query_args = $this->restrict_comment_status( $query_args );
		$query_args = $this->restrict_content_status( $query_args );
		$query_args = $this->restrict_comment_type( $query_args );
		$query_args = $this->restrict_search( $query_args );
		$query_args = $this->restrict_orderby( $query_args );
		$query_args = $this->restrict_content( $query_args );

		// Users still get their own unapproved comments.
		if ( get_current_user_id() ) {
			$query_args['include_unapproved'] = [ get_current_user_id() ];
		} else {
			unset( $query_args['include_unapproved'] );
		}

		return $query_args;
	}

	/**
	 * The author's email is only visible to moderators, so filtering by it matches no comments.
	 *
	 * The condition is replaced with one that matches nothing, rather than dropped, since dropping it would
	 * return every comment if the query ran anyway.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_author_email( array $query_args ): array {
		if ( empty( $query_args['author_email'] ) ) {
			return $query_args;
		}

		unset( $query_args['author_email'] );

		return $this->match_nothing( $query_args );
	}

	/**
	 * Only approved comments can be filtered by. Other statuses are ignored, matching how post status
	 * filters behave, and when none are left the query matches nothing.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_comment_status( array $query_args ): array {
		if ( ! isset( $query_args['status'] ) || 'approve' === $query_args['status'] ) {
			return $query_args;
		}

		$requested = $this->parse_list( $query_args['status'] );

		$query_args['status'] = 'approve';

		// An empty status, "all" and "any" match every status, which for this user means approved comments.
		if ( ! empty( $requested ) && ! array_intersect( $requested, [ 'approve', '1', 'all', 'any' ] ) ) {
			$query_args = $this->match_nothing( $query_args );
		}

		return $query_args;
	}

	/**
	 * Only public content statuses can be filtered by. Other statuses are ignored, and when none are left
	 * the query matches nothing.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_content_status( array $query_args ): array {
		if ( empty( $query_args['post_status'] ) ) {
			return $query_args;
		}

		$allowed = array_values( array_intersect( $this->parse_list( $query_args['post_status'] ), $this->get_public_post_statuses() ) );

		if ( empty( $allowed ) ) {
			$query_args = $this->match_nothing( $query_args );
			$allowed    = [ 'publish' ];
		}

		$query_args['post_status'] = $allowed;

		return $query_args;
	}

	/**
	 * Editorial notes are only visible to users who can edit them, so they're left out of the query.
	 *
	 * WordPress already leaves notes out unless they're asked for by type, or with the "all" type. "all"
	 * becomes no type filter, which is every type except notes, and a type list that only names notes
	 * matches nothing.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_comment_type( array $query_args ): array {
		foreach ( [ 'type', 'type__in' ] as $key ) {
			if ( empty( $query_args[ $key ] ) ) {
				continue;
			}

			$requested = is_array( $query_args[ $key ] ) || is_string( $query_args[ $key ] ) ? wp_parse_list( $query_args[ $key ] ) : [];

			if ( in_array( 'all', $requested, true ) ) {
				unset( $query_args[ $key ] );
				continue;
			}

			$allowed = array_values( array_diff( $requested, [ 'note' ] ) );

			if ( empty( $allowed ) ) {
				unset( $query_args[ $key ] );
				$query_args = $this->match_nothing( $query_args );
				continue;
			}

			$query_args[ $key ] = $allowed;
		}

		return $query_args;
	}

	/**
	 * Search only the fields the user can read.
	 *
	 * WordPress's comment search also matches the author's email and IP address, which only moderators can
	 * see, so the term is passed on as `graphql_search_visible_fields` instead, which only searches the
	 * author's name and URL and the comment's content (see Config::graphql_wp_comments_query_visible_fields_search()).
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_search( array $query_args ): array {
		if ( ! isset( $query_args['search'] ) || '' === $query_args['search'] ) {
			return $query_args;
		}

		$query_args['graphql_search_visible_fields'] = is_scalar( $query_args['search'] ) ? (string) $query_args['search'] : '';
		unset( $query_args['search'] );

		return $query_args;
	}

	/**
	 * Ordering by a field only moderators can see falls back to the comment date, since the order, and
	 * cursors built from it, would show how those values compare.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_orderby( array $query_args ): array {
		if ( empty( $query_args['orderby'] ) ) {
			return $query_args;
		}

		$hidden_fields = [ 'comment_author_email', 'comment_author_ip', 'comment_agent' ];
		$is_hidden     = static function ( $field ) use ( $hidden_fields ): bool {
			return in_array( strtolower( (string) $field ), $hidden_fields, true );
		};

		$orderby = $query_args['orderby'];

		$is_visible = static function ( $field ) use ( $is_hidden ): bool {
			return ! $is_hidden( $field );
		};

		if ( is_array( $orderby ) ) {
			// Either a list of fields, or fields mapped to their order.
			$orderby = array_values( $orderby ) === $orderby
				? array_values( array_filter( $orderby, $is_visible ) )
				: array_filter( $orderby, $is_visible, ARRAY_FILTER_USE_KEY );
		} elseif ( is_string( $orderby ) ) {
			$fields  = preg_split( '/[,\s]+/', $orderby, -1, PREG_SPLIT_NO_EMPTY );
			$orderby = implode( ' ', array_filter( false !== $fields ? $fields : [], $is_visible ) );
		}

		$query_args['orderby'] = ! empty( $orderby ) ? $orderby : 'comment_date';

		return $query_args;
	}

	/**
	 * Limits the query to comments on content the user can see.
	 *
	 * Comments on content the user can't see are hidden when the comments load, but pagination info is
	 * decided from the rows the query returns, so those comments must not be queried at all.
	 *
	 * - The comments of a content node need nothing more: the node was already visible to the user.
	 * - A reply or parent is limited to the content of the comment it's queried from.
	 * - A query limited to specific content keeps only the content the user can see, judged by the content's
	 *   own visibility (the Post model), so a media item follows the content it's attached to.
	 * - Any other query only returns comments on content with a public status, of a post type that's public.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function restrict_content( array $query_args ): array {
		if ( $this->source instanceof Post ) {
			return $query_args;
		}

		// A reply or parent can be stored on other content than the comment it's queried from.
		if ( $this->source instanceof Comment ) {
			$source_comment = get_comment( $this->source->databaseId );
			$source_post_id = $source_comment instanceof \WP_Comment ? absint( $source_comment->comment_post_ID ) : 0;
			$post_in        = ! empty( $query_args['post__in'] ) ? wp_parse_id_list( $query_args['post__in'] ) : [ $source_post_id ];
			$post_in        = array_values( array_intersect( $post_in, [ $source_post_id ] ) );

			if ( empty( $post_in ) ) {
				$query_args = $this->match_nothing( $query_args );
				$post_in    = [ 0 ];
			}

			$query_args['post__in'] = $post_in;
		}

		$post_id = isset( $query_args['post_id'] ) && is_numeric( $query_args['post_id'] ) ? absint( $query_args['post_id'] ) : 0;
		$post_in = ! empty( $query_args['post__in'] ) ? wp_parse_id_list( $query_args['post__in'] ) : [];

		if ( 0 === $post_id && empty( $post_in ) ) {
			if ( empty( $query_args['post_status'] ) ) {
				$query_args['post_status'] = $this->get_public_post_statuses();
			}

			$public_post_types = $this->get_public_post_types();
			$post_types        = ! empty( $query_args['post_type'] ) ? array_values( array_intersect( $this->parse_list( $query_args['post_type'] ), $public_post_types ) ) : $public_post_types;

			if ( empty( $post_types ) ) {
				$query_args = $this->match_nothing( $query_args );
				$post_types = $public_post_types;
			}

			$query_args['post_type'] = $post_types;

			return $query_args;
		}

		if ( 0 !== $post_id && ! $this->is_content_visible( $post_id ) ) {
			$query_args = $this->match_nothing( $query_args );
		}

		if ( ! empty( $post_in ) ) {
			_prime_post_caches( $post_in, false, false );

			$visible = array_values(
				array_filter(
					$post_in,
					function ( $id ) {
						return $this->is_content_visible( $id );
					}
				)
			);

			if ( empty( $visible ) ) {
				// An empty list would remove the condition, so never leave one behind.
				$query_args = $this->match_nothing( $query_args );
				$visible    = [ 0 ];
			}

			$query_args['post__in'] = $visible;
		}

		return $query_args;
	}

	/**
	 * Makes the query match no comments, and skips running it.
	 *
	 * The impossible condition keeps the query from matching anything even if a
	 * `graphql_connection_should_execute` callback runs it anyway.
	 *
	 * @param array<string,mixed> $query_args The WP_Comment_Query args.
	 *
	 * @return array<string,mixed>
	 */
	private function match_nothing( array $query_args ): array {
		$this->should_execute      = false;
		$query_args['comment__in'] = [ 0 ];

		return $query_args;
	}

	/**
	 * Parses a list of slugs given as an array or a comma or space separated string.
	 *
	 * @param mixed $value The value to parse.
	 *
	 * @return string[]
	 */
	private function parse_list( $value ): array {
		return is_array( $value ) || is_string( $value ) ? wp_parse_slug_list( $value ) : [];
	}

	/**
	 * The registered post statuses whose content is public.
	 *
	 * @return string[]
	 */
	private function get_public_post_statuses(): array {
		return array_values( get_post_stati( [ 'public' => true ] ) );
	}

	/**
	 * The post types whose published content is public, decided the same way the Post model decides it.
	 *
	 * @return string[]
	 */
	private function get_public_post_types(): array {
		$post_types = array_merge( get_post_types( [ 'public' => true ] ), get_post_types( [ 'publicly_queryable' => true ] ) );

		return array_values( array_diff( array_unique( $post_types ), [ 'revision' ] ) );
	}

	/**
	 * Whether the user can see the content a comment is on, judged by the content's own visibility.
	 *
	 * @param int $post_id The ID of the content.
	 */
	private function is_content_visible( int $post_id ): bool {
		// get_post() falls back to the global post for an empty ID.
		if ( $post_id <= 0 ) {
			return false;
		}

		$post = get_post( $post_id );

		return $post instanceof \WP_Post && ! ( new Post( $post ) )->is_private();
	}

	/**
	 * This sets up the "allowed" args, and translates the GraphQL-friendly keys to
	 * WP_Comment_Query friendly keys.
	 *
	 * There's probably a cleaner/more dynamic way to approach this, but this was quick. I'd be
	 * down to explore more dynamic ways to map this, but for now this gets the job done.
	 *
	 * @param array<string,mixed> $args The array of query arguments
	 *
	 * @since  0.0.5
	 * @return array<string,mixed>
	 */
	public function sanitize_input_fields( array $args ) {
		$arg_mapping = [
			'authorEmail'        => 'author_email',
			'authorIn'           => 'author__in',
			'authorNotIn'        => 'author__not_in',
			'authorUrl'          => 'author_url',
			'commentIn'          => 'comment__in',
			'commentNotIn'       => 'comment__not_in',
			'commentType'        => 'type',
			'commentTypeIn'      => 'type__in',
			'commentTypeNotIn'   => 'type__not_in',
			'contentAuthor'      => 'post_author',
			'contentAuthorIn'    => 'post_author__in',
			'contentAuthorNotIn' => 'post_author__not_in',
			'contentId'          => 'post_id',
			'contentIdIn'        => 'post__in',
			'contentIdNotIn'     => 'post__not_in',
			'contentName'        => 'post_name',
			'contentParent'      => 'post_parent',
			'contentStatus'      => 'post_status',
			'contentType'        => 'post_type',
			'includeUnapproved'  => 'include_unapproved',
			'parentIn'           => 'parent__in',
			'parentNotIn'        => 'parent__not_in',
			'statusIn'           => 'status',
			'userId'             => 'user_id',
		];

		/**
		 * Map and sanitize the input args to the WP_Comment_Query compatible args
		 */
		$query_args = Utils::map_input( $args, $arg_mapping );

		/**
		 * Filter the input fields
		 *
		 * This allows plugins/themes to hook in and alter what $args should be allowed to be passed
		 * from a GraphQL Query to the get_terms query
		 *
		 * @param array<string,mixed>                  $query_args             The mapped query args to send to WP_Comment_Query.
		 * @param array<string,mixed>                  $args                   The incoming GraphQL where args before mapping.
		 * @param mixed                                $source                 The source node passed down the resolve tree.
		 * @param array<string,mixed>                  $all_args               All GraphQL args provided to the connection field.
		 * @param \WPGraphQL\AppContext                $context                The AppContext passed down the resolve tree.
		 * @param \GraphQL\Type\Definition\ResolveInfo $info                   ResolveInfo for the current field.
		 *
		 * @hookGroup connections
		 * @since 0.0.5
		 */
		$query_args = apply_filters( 'graphql_map_input_fields_to_wp_comment_query', $query_args, $args, $this->source, $this->get_args(), $this->context, $this->info );

		return ! empty( $query_args ) && is_array( $query_args ) ? $query_args : [];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param int $offset The ID of the node used for the cursor offset.
	 */
	public function is_valid_offset( $offset ) {
		$comment = get_comment( $offset );

		// A comment the user can't see is treated like one that doesn't exist.
		return $comment instanceof \WP_Comment && 'private' !== ( new Comment( $comment ) )->get_visibility();
	}
}
