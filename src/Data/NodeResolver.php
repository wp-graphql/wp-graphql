<?php

namespace WPGraphQL\Data;

use Exception;
use WP;
use WP_Post;
use WPGraphQL\AppContext;
use WPGraphQL\Model\Post;

class NodeResolver {

	/**
	 * @var WP
	 */
	protected $wp;

	/**
	 * @var AppContext
	 */
	protected $context;

	/**
	 * NodeResolver constructor.
	 *
	 * @param AppContext $context
	 *
	 * @return void
	 */
	public function __construct( AppContext $context ) {
		global $wp;
		$this->wp      = $wp;
		$this->context = $context;
	}

	/**
	 * Given a Post object, validates it before returning it.
	 *
	 * @param WP_Post $post
	 *
	 * @return WP_Post|null
	 */
	public function validate_post( WP_Post $post ) {

		if ( isset( $this->wp->query_vars['post_type'] ) && ( $post->post_type !== $this->wp->query_vars['post_type'] ) ) {
			return null;
		}

		if ( ! isset( $this->wp->query_vars['uri'] ) ) {
			return $post;
		}

		$permalink    = get_permalink( $post );
		$parsed_path  = $permalink ? wp_parse_url( $permalink, PHP_URL_PATH ) : null;
		$trimmed_path = $parsed_path ? rtrim( ltrim( $parsed_path, '/' ), '/' ) : null;
		$uri_path     = rtrim( ltrim( $this->wp->query_vars['uri'], '/' ), '/' );
		if ( $trimmed_path !== $uri_path ) {
			return null;
		}

		return $post;
	}

	/**
	 * Given the URI of a resource, this method attempts to resolve it and return the
	 * appropriate related object
	 *
	 * @param string       $uri              The path to be used as an identifier for the
	 *                                             resource.
	 * @param mixed|array|string $extra_query_vars Any extra query vars to consider
	 *
	 * @return mixed
	 * @throws Exception
	 */
	public function resolve_uri( string $uri, $extra_query_vars = '' ) {

		/**
		 * When this filter return anything other than null, it will be used as a resolved node
		 * and the execution will be skipped.
		 *
		 * This is to be used in extensions to resolve their own nodes which might not use
		 * WordPress permalink structure.
		 *
		 * @param mixed|null $node The node, defaults to nothing.
		 * @param string $uri The uri being searched.
		 * @param AppContext $content The app context.
		 * @param WP $wp WP object.
		 * @param mixed|array|string $extra_query_vars Any extra query vars to consider.
		 */
		$node = apply_filters( 'graphql_pre_resolve_uri', null, $uri, $this->context, $this->wp, $extra_query_vars );

		if ( ! empty( $node ) ) {
			return $node;
		}

		$uri = $this->parse_request( $uri, $extra_query_vars );

		// Resolve the homepage.
		if ( '/' === $uri ) {

			$page_id       = get_option( 'page_on_front', 0 );
			$show_on_front = get_option( 'show_on_front', 'posts' );

			// If the homepage is a static page, return the page.
			if ( 'page' === $show_on_front && ! empty( $page_id ) ) {

				$page = get_post( $page_id );

				if ( empty( $page ) ) {
					return null;
				}

				return new Post( $page );
			}

			// If the homepage is set to latest posts, we need to make sure not to resolve it when when querying for `ContentNode`s.
			if ( isset( $this->wp->query_vars['nodeType'] ) && 'ContentNode' === $this->wp->query_vars['nodeType'] ) {
				return null;
			}

			// We dont have an 'Archive' type, so we resolve to the ContentType.
			return $this->context->get_loader( 'post_type' )->load_deferred( 'post' );
		}

		// Resolve a page by ID.
		if ( isset( $this->wp->query_vars['page_id'] ) ) {
			return absint( $this->wp->query_vars['page_id'] ) ? $this->context->get_loader( 'post' )->load_deferred( absint( $this->wp->query_vars['page_id'] ) ) : null;
		}

		// Resolve a post by ID.
		if ( isset( $this->wp->query_vars['p'] ) ) {
			return absint( $this->wp->query_vars['p'] ) ? $this->context->get_loader( 'post' )->load_deferred( absint( $this->wp->query_vars['p'] ) ) : null;
		}

		// Resolve a post by its slug.
		if ( isset( $this->wp->query_vars['name'] ) ) {

			// Target post types with a public URI.
			$allowed_post_types = \WPGraphQL::get_allowed_post_types();

			$post_type = 'post';
			if ( isset( $this->wp->query_vars['post_type'] ) && in_array( $this->wp->query_vars['post_type'], $allowed_post_types, true ) ) {
				$post_type = $this->wp->query_vars['post_type'];
			}

			$post = get_page_by_path( $this->wp->query_vars['name'], 'OBJECT', $post_type ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path

			unset( $this->wp->query_vars['uri'] );
			$post = $post instanceof WP_Post ? $this->validate_post( $post ) : null;

			return isset( $post->ID ) ? $this->context->get_loader( 'post' )->load_deferred( $post->ID ) : null;
		}

		// Resolve a category term by its ID.
		if ( isset( $this->wp->query_vars['cat'] ) ) {
			$node = get_term( absint( $this->wp->query_vars['cat'] ), 'category' );

			return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( (int) $node->term_id ) : null;
		}

		// Resolve a tag term by its slug.
		if ( isset( $this->wp->query_vars['tag'] ) ) {
			$node = get_term_by( 'slug', $this->wp->query_vars['tag'], 'post_tag' );

			return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( (int) $node->term_id ) : null;
		}

		// Resolve a post by its URI.
		if ( isset( $this->wp->query_vars['pagename'] ) && ! empty( $this->wp->query_vars['pagename'] ) ) {

			unset( $this->wp->query_vars['uri'] );

			$post_type = isset( $this->wp->query_vars['post_type'] ) ? $this->wp->query_vars['post_type'] : \WPGraphQL::get_allowed_post_types();

			$post = get_page_by_path( $this->wp->query_vars['pagename'], 'OBJECT', $post_type ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path

			if ( ! $post instanceof WP_Post ) {
				return null;
			}

			$post = $this->validate_post( $post );

			if ( ! $post ) {
				return null;
			}

			if ( (int) get_option( 'page_for_posts', 0 ) === $post->ID ) {

				if ( isset( $extra_query_vars['nodeType'] ) && 'ContentNode' === $extra_query_vars['nodeType'] ) {
					return null;
				}

				return $this->context->get_loader( 'post_type' )->load_deferred( 'post' );
			}

			return $this->context->get_loader( 'post' )->load_deferred( $post->ID );
		}

		// Resolve a user by its author name.
		if ( isset( $this->wp->query_vars['author_name'] ) ) {
			$user = get_user_by( 'slug', $this->wp->query_vars['author_name'] );

			return isset( $user->ID ) ? $this->context->get_loader( 'user' )->load_deferred( $user->ID ) : null;
		}

		// Resolve a category term by its name.
		if ( isset( $this->wp->query_vars['category_name'] ) ) {
			$term = get_category_by_path( $this->wp->query_vars['category_name'] );
			if ( ! $term instanceof \WP_Term ) {
				return null;
			}
			$node = get_term_by( 'id', $term->term_id, 'category' );
			return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( $node->term_id ) : null;
		}

		/**
		 * If we know the post_type, we can try again to resolve the ContentNode.
		 *
		 * Failing that we just resolve to the post type.
		 */
		if ( isset( $this->wp->query_vars['post_type'] ) ) {

			// If the query is asking for a Page nodeType with the home uri, try and resolve it.
			if ( '/' === $this->wp->query_vars['uri'] && ( isset( $this->wp->query_vars['nodeType'] ) && 'ContentNode' === $this->wp->query_vars['nodeType'] ) ) {

				// If the post type is not a page, but the uri is for the home page, we can return null now
				if ( 'page' !== $this->wp->query_vars['post_type'] ) {
					return null;
				}

				$page_on_front = get_option( 'page_on_front', 0 );
				$post          = get_post( absint( $page_on_front ) );
				return ! empty( $post ) ? $this->context->get_loader( 'post' )->load_deferred( $post->ID ) : null;
			}

			// If the query is asking for a Page nodeType with the uri, try and resolve it.
			if ( isset( $this->wp->query_vars['nodeType'] ) && 'ContentNode' === $this->wp->query_vars['nodeType'] && isset( $this->wp->query_vars['uri'] ) ) {
				$post_type = $this->wp->query_vars['post_type'];

				$post = get_page_by_path( $this->wp->query_vars['uri'], 'OBJECT', $post_type ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path

				$post = isset( $post->ID ) ? $this->validate_post( $post ) : null;
				return isset( $post->ID ) ? $this->context->get_loader( 'post' )->load_deferred( $post->ID ) : null;
			}

			$post_type_object = get_post_type_object( $this->wp->query_vars['post_type'] );

			return ! empty( $post_type_object ) ? $this->context->get_loader( 'post_type' )->load_deferred( $post_type_object->name ) : null;
		}
		
		/**
		 * If we're still here, check if we're trying to query a taxonomy term.
		 */
		$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ], 'objects' );
		foreach ( $taxonomies as $tax_object ) {
			// Resolve a taxonomy term by its slug.
			if ( isset( $this->wp->query_vars[ $tax_object->query_var ] ) ) {
				$node = get_term_by( 'slug', $this->wp->query_vars[ $tax_object->query_var ], $tax_object->name );

				return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( $node->term_id ) : null;
			}
		}

		return $node;
	}

	/**
	 * Parses a URL to produce an array of query variables.
	 *
	 * Mimics WP::parse_request()
	 *
	 * @param string $uri
	 * @param array|string $extra_query_vars
	 *
	 * @return string|null The parsed uri.
	 */
	public function parse_request( string $uri, $extra_query_vars = '' ) {
		// Attempt to parse the provided URI.
		$parsed_url = wp_parse_url( $uri );

		if ( false === $parsed_url ) {
			graphql_debug( __( 'Cannot parse provided URI', 'wp-graphql' ), [
				'uri' => $uri,
			] );
			return null;
		}

		// Bail if external URI.
		if ( isset( $parsed_url['host'] ) ) {
			$site_url = wp_parse_url( site_url() );
			$home_url = wp_parse_url( home_url() );

			/**
			 * @var array $home_url
			 * @var array $site_url
			 */
			if ( ! in_array(
				$parsed_url['host'],
				[
					$site_url['host'],
					$home_url['host'],
				],
				true
			) ) {
				graphql_debug( __( 'Cannot return a resource for an external URI', 'wp-graphql' ), [
					'uri' => $uri,
				] );
				return null;
			}
		}

		if ( isset( $parsed_url['query'] ) && ( empty( $parsed_url['path'] ) || '/' === $parsed_url['path'] ) ) {
			$uri = $parsed_url['query'];
		} elseif ( isset( $parsed_url['path'] ) ) {
			$uri = $parsed_url['path'];
		}

		/**
		 * Follows pattern from WP::parse_request()
		 *
		 * @see https://github.com/WordPress/wordpress-develop/blob/6.0.2/src/wp-includes/class-wp.php#L135
		 */
		global $wp_rewrite;


		$this->wp->query_vars = [];
		$post_type_query_vars = [];


		if ( is_array( $extra_query_vars ) ) {
			$this->wp->query_vars = &$extra_query_vars;
		} elseif ( ! empty( $extra_query_vars ) ) {
			parse_str( $extra_query_vars, $this->wp->extra_query_vars );
		}

		// Set uri to Query vars.
		$this->wp->query_vars['uri'] = $uri;

		// Process PATH_INFO, REQUEST_URI, and 404 for permalinks.

		// Fetch the rewrite rules.
		$rewrite = $wp_rewrite->wp_rewrite_rules();

		$error = '404';
		if ( ! empty( $rewrite ) ) {
			// If we match a rewrite rule, this will be cleared.
			$error                   = null;
			$this->wp->did_permalink = true;

			$pathinfo         = ! empty( $uri ) ? $uri : '';
			list( $pathinfo ) = explode( '?', $pathinfo );
			$pathinfo         = str_replace( '%', '%25', $pathinfo );

			list( $req_uri ) = explode( '?', $pathinfo );
			$home_path       = parse_url( home_url(), PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			$home_path_regex = '';
			if ( is_string( $home_path ) && '' !== $home_path ) {
				$home_path       = trim( $home_path, '/' );
				$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );
			}

			/*
			 * Trim path info from the end and the leading home path from the front.
			 * For path info requests, this leaves us with the requesting filename, if any.
			 * For 404 requests, this leaves us with the requested permalink.
			 */
			$query    = '';
			$matches  = null;
			$req_uri  = str_replace( $pathinfo, '', $req_uri );
			$req_uri  = trim( $req_uri, '/' );
			$pathinfo = trim( $pathinfo, '/' );

			if ( ! empty( $home_path_regex ) ) {
				$req_uri  = preg_replace( $home_path_regex, '', $req_uri );
				$req_uri  = trim( $req_uri, '/' ); // @phpstan-ignore-line
				$pathinfo = preg_replace( $home_path_regex, '', $pathinfo );
				$pathinfo = trim( $pathinfo, '/' ); // @phpstan-ignore-line
			}

			// The requested permalink is in $pathinfo for path info requests and
			// $req_uri for other requests.
			if ( ! empty( $pathinfo ) && ! preg_match( '|^.*' . $wp_rewrite->index . '$|', $pathinfo ) ) {
				$requested_path = $pathinfo;
			} else {
				// If the request uri is the index, blank it out so that we don't try to match it against a rule.
				if ( $req_uri === $wp_rewrite->index ) {
					$req_uri = '';
				}
				$requested_path = $req_uri;
			}
			$requested_file = $req_uri;

			$this->wp->request = $requested_path;

			// Look for matches.
			$request_match = $requested_path;
			if ( empty( $request_match ) ) {
				// An empty request could only match against ^$ regex
				if ( isset( $rewrite['$'] ) ) {
					$this->wp->matched_rule = '$';
					$query                  = $rewrite['$'];
					$matches                = [ '' ];
				}
			} else {
				foreach ( (array) $rewrite as $match => $query ) {
					// If the requested file is the anchor of the match, prepend it to the path info.
					if ( ! empty( $requested_file ) && strpos( $match, $requested_file ) === 0 && $requested_file !== $requested_path ) {
						$request_match = $requested_file . '/' . $requested_path;
					}

					if (
						preg_match( "#^$match#", $request_match, $matches ) ||
						preg_match( "#^$match#", urldecode( $request_match ), $matches )
					) {

						if ( $wp_rewrite->use_verbose_page_rules && preg_match( '/pagename=\$matches\[([0-9]+)\]/', $query, $varmatch ) ) {
							// This is a verbose page match, let's check to be sure about it.
							$page = get_page_by_path( $matches[ $varmatch[1] ] ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.get_page_by_path_get_page_by_path
							if ( ! $page ) {
								continue;
							}

							$post_status_obj = get_post_status_object( $page->post_status );
							if (
								( ! isset( $post_status_obj->public ) || ! $post_status_obj->public ) &&
								( ! isset( $post_status_obj->protected ) || ! $post_status_obj->protected ) &&
								( ! isset( $post_status_obj->private ) || ! $post_status_obj->private ) &&
								( ! isset( $post_status_obj->exclude_from_search ) || $post_status_obj->exclude_from_search )
							) {
								continue;
							}
						}

						// Got a match.
						$this->wp->matched_rule = $match;
						break;
					}
				}
			}

			if ( ! empty( $this->wp->matched_rule ) ) {
				// Trim the query of everything up to the '?'.
				$query = preg_replace( '!^.+\?!', '', $query );

				// Substitute the substring matches into the query.
				$query = addslashes( \WP_MatchesMapRegex::apply( $query, $matches ) );

				// Parse the query.
				parse_str( $query, $perma_query_vars );

			}
		}

		/**
		 * Filters the query variables allowed before processing.
		 *
		 * Allows (publicly allowed) query vars to be added, removed, or changed prior
		 * to executing the query. Needed to allow custom rewrite rules using your own arguments
		 * to work, or any other custom query variables you want to be publicly available.
		 *
		 * @since 1.5.0
		 *
		 * @param string[] $public_query_vars The array of allowed query variable names.
		 */
		$this->wp->public_query_vars = apply_filters( 'query_vars', $this->wp->public_query_vars );


		foreach ( get_post_types( [ 'show_in_graphql' => true ], 'objects' )  as $post_type => $t ) {
			/** @var \WP_Post_Type $t */
			if ( $t->query_var ) {
				$post_type_query_vars[ $t->query_var ] = $post_type;
			}
		}

		foreach ( $this->wp->public_query_vars as $wpvar ) {

			$parsed_query = [];
			if ( isset( $parsed_url['query'] ) ) {
				parse_str( $parsed_url['query'], $parsed_query );
			}

			if ( isset( $this->wp->extra_query_vars[ $wpvar ] ) ) {
				$this->wp->query_vars[ $wpvar ] = $this->wp->extra_query_vars[ $wpvar ];
			} elseif ( isset( $_GET[ $wpvar ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
				$this->wp->query_vars[ $wpvar ] = $_GET[ $wpvar ]; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.NonceVerification.Recommended
			} elseif ( isset( $perma_query_vars[ $wpvar ] ) ) {
				$this->wp->query_vars[ $wpvar ] = $perma_query_vars[ $wpvar ];
			} elseif ( isset( $parsed_query[ $wpvar ] ) ) {
				$this->wp->query_vars[ $wpvar ] = $parsed_query[ $wpvar ];
			}

			if ( ! empty( $this->wp->query_vars[ $wpvar ] ) ) {
				if ( ! is_array( $this->wp->query_vars[ $wpvar ] ) ) {
					$this->wp->query_vars[ $wpvar ] = (string) $this->wp->query_vars[ $wpvar ];
				} else {
					foreach ( $this->wp->query_vars[ $wpvar ] as $vkey => $v ) {
						if ( is_scalar( $v ) ) {
							$this->wp->query_vars[ $wpvar ][ $vkey ] = (string) $v;
						}
					}
				}

				if ( isset( $post_type_query_vars[ $wpvar ] ) ) {
					$this->wp->query_vars['post_type'] = $post_type_query_vars[ $wpvar ];
					$this->wp->query_vars['name']      = $this->wp->query_vars[ $wpvar ];
				}
			}
		}

		// Convert urldecoded spaces back into '+'.
		foreach ( get_taxonomies( [ 'show_in_graphql' => true ], 'objects' ) as $taxonomy => $t ) {
			if ( $t->query_var && isset( $this->wp->query_vars[ $t->query_var ] ) ) {
				$this->wp->query_vars[ $t->query_var ] = str_replace( ' ', '+', $this->wp->query_vars[ $t->query_var ] );
			}
		}

		// Limit publicly queried post_types to those that are publicly_queryable
		if ( isset( $this->wp->query_vars['post_type'] ) ) {
			$queryable_post_types = get_post_types( [ 'show_in_graphql' => true ] );
			if ( ! is_array( $this->wp->query_vars['post_type'] ) ) {
				if ( ! in_array( $this->wp->query_vars['post_type'], $queryable_post_types, true ) ) {
					unset( $this->wp->query_vars['post_type'] );
				}
			} else {
				$this->wp->query_vars['post_type'] = array_intersect( $this->wp->query_vars['post_type'], $queryable_post_types );
			}
		}

		// Resolve conflicts between posts with numeric slugs and date archive queries.
		$this->wp->query_vars = wp_resolve_numeric_slug_conflicts( $this->wp->query_vars );

		foreach ( (array) $this->wp->private_query_vars as $var ) {
			if ( isset( $this->wp->extra_query_vars[ $var ] ) ) {
				$this->wp->query_vars[ $var ] = $this->wp->extra_query_vars[ $var ];
			}
		}

		if ( isset( $error ) ) {
			$this->wp->query_vars['error'] = $error;
		}

		/**
		 * Filters the array of parsed query variables.
		 *
		 * @param array $query_vars The array of requested query variables.
		 *
		 * @since 2.1.0
		 */
		$this->wp->query_vars = apply_filters( 'request', $this->wp->query_vars );

		// We don't need the GraphQL args anymore.
		unset( $this->wp->query_vars['graphql'] );

		do_action_ref_array( 'parse_request', [ &$this->wp ] );

		return $uri;
	}
}
