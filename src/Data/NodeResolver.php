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
		$parsed_path  = $permalink ? parse_url( $permalink, PHP_URL_PATH ) : null;
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
		 * @param null $node The node, defaults to nothing.
		 * @param string $uri The uri being searched.
		 * @param AppContext $content The app context.
		 * @param WP $wp WP object.
		 */
		$node = apply_filters( 'graphql_pre_resolve_uri', null, $uri, $this->context, $this->wp );

		if ( ! empty( $node ) ) {
			return $node;
		}

		global $wp_rewrite;

		$parsed_url = wp_parse_url( $uri );

		if ( isset( $parsed_url['host'] ) ) {
			if ( ! in_array(
				$parsed_url['host'],
				[
					wp_parse_url( site_url() )['host'],
					wp_parse_url( home_url() )['host'],
				],
				true
			) ) {
				graphql_debug( __( 'Cannot return a resource for an external URI', 'wp-graphql' ), [
					'uri' => $uri,
				] );
				return null;
			}
		}

		$this->wp->query_vars = [];
		$post_type_query_vars = [];

		if ( isset( $parsed_url['query'] ) && '/' === $parsed_url['path'] ) {
			$uri = $parsed_url['query'];
		} elseif ( isset( $parsed_url['path'] ) ) {
			$uri = $parsed_url['path'];
		}

		if ( is_array( $extra_query_vars ) ) {
			$this->wp->query_vars = &$extra_query_vars;
		} elseif ( ! empty( $extra_query_vars ) ) {
			parse_str( $extra_query_vars, $this->wp->extra_query_vars );
		}

		$this->wp->query_vars['uri'] = $uri;
		// Process PATH_INFO, REQUEST_URI, and 404 for permalinks.

		// Fetch the rewrite rules.
		$rewrite = $wp_rewrite->wp_rewrite_rules();

		$error = '404';
		if ( ! empty( $rewrite ) ) {
			// If we match a rewrite rule, this will be cleared.
			$error                   = null;
			$this->wp->did_permalink = true;

			$pathinfo         = isset( $uri ) ? $uri : '';
			list( $pathinfo ) = explode( '?', $pathinfo );
			$pathinfo         = str_replace( '%', '%25', $pathinfo );

			list( $req_uri ) = explode( '?', $pathinfo );
			$home_path       = trim( wp_parse_url( home_url(), PHP_URL_PATH ), '/' );
			$home_path_regex = sprintf( '|^%s|i', preg_quote( $home_path, '|' ) );

			// Trim path info from the end and the leading home path from the
			// front. For path info requests, this leaves us with the requesting
			// filename, if any. For 404 requests, this leaves us with the
			// requested permalink.
			$query        = '';
			$matches      = null;
			$req_uri      = str_replace( $pathinfo, '', $req_uri );
			$req_uri      = trim( $req_uri, '/' );
			$replaced_uri = preg_replace( $home_path_regex, '', $req_uri );

			if ( ! empty( $replaced_uri ) ) {
				$req_uri = $replaced_uri;
			}

			$req_uri           = trim( $req_uri, '/' );
			$pathinfo          = trim( $pathinfo, '/' );
			$replaced_pathinfo = preg_replace( $home_path_regex, '', $pathinfo );
			if ( ! empty( $replaced_pathinfo ) ) {
				$pathinfo = $replaced_pathinfo;
			}
			$pathinfo = trim( $pathinfo, '/' );

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
							$page = get_page_by_path( $matches[ $varmatch[1] ] );
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

			if ( isset( $this->wp->matched_rule ) ) {

				// Trim the query of everything up to the '?'.
				$query = preg_replace( '!^.+\?!', '', $query );

				// Substitute the substring matches into the query.
				$query = addslashes( \WP_MatchesMapRegex::apply( $query, $matches ) );

				// Parse the query.
				parse_str( $query, $perma_query_vars );

			}
		}

		/**
		 * Filters the query variables whitelist before processing.
		 *
		 * Allows (publicly allowed) query vars to be added, removed, or changed prior
		 * to executing the query. Needed to allow custom rewrite rules using your own arguments
		 * to work, or any other custom query variables you want to be publicly available.
		 *
		 * @param string[] $public_query_vars The array of whitelisted query variable names.
		 *
		 * @since 1.5.0
		 */
		$this->wp->public_query_vars = apply_filters( 'query_vars', $this->wp->public_query_vars );

		$post_type_objects = get_post_types( [ 'show_in_graphql' => true ], 'objects' );

		if ( ! empty( $post_type_objects ) ) {
			foreach ( $post_type_objects as $post_type_object ) {
				if ( isset( $post_type_object->show_in_graphql ) && true === $post_type_object->show_in_graphql && $post_type_object->query_var ) {
					$post_type_query_vars[ $post_type_object->query_var ] = $post_type_object->name;
				}
			}
		}

		foreach ( $this->wp->public_query_vars as $wpvar ) {

			$parsed_query = [];
			if ( isset( $parsed_url['query'] ) ) {
				parse_str( $parsed_url['query'], $parsed_query );
			}

			if ( isset( $this->wp->extra_query_vars[ $wpvar ] ) ) {
				$this->wp->query_vars[ $wpvar ] = $this->wp->extra_query_vars[ $wpvar ];
			} elseif ( isset( $_GET[ $wpvar ] ) ) {
				$this->wp->query_vars[ $wpvar ] = $_GET[ $wpvar ];
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

		// Convert urldecoded spaces back into +
		foreach ( get_taxonomies( [], 'objects' ) as $taxonomy => $t ) {
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

		unset( $this->wp->query_vars['graphql'] );

		do_action_ref_array( 'parse_request', [ $this->wp ] );

		// If the request is for the homepage, determine
		if ( '/' === $uri ) {

			$page_id       = get_option( 'page_on_front', 0 );
			$show_on_front = get_option( 'show_on_front', 'posts' );

			if ( 'page' === $show_on_front && ! empty( $page_id ) ) {

				if ( empty( $page_id ) ) {
					return null;
				}
				$page = get_post( $page_id );

				if ( empty( $page ) ) {
					return null;
				}

				return new Post( $page );

			} else {

				if ( isset( $this->wp->query_vars['nodeType'] ) && 'Page' === $this->wp->query_vars['nodeType'] ) {
					return null;
				}

				return $this->context->get_loader( 'post_type' )->load_deferred( 'post' );
			}
		}

		if ( isset( $this->wp->query_vars['page_id'] ) ) {
			return absint( $this->wp->query_vars['page_id'] ) ? $this->context->get_loader( 'post' )->load_deferred( absint( $this->wp->query_vars['page_id'] ) ) : null;
		} elseif ( isset( $this->wp->query_vars['p'] ) ) {
			return absint( $this->wp->query_vars['p'] ) ? $this->context->get_loader( 'post' )->load_deferred( absint( $this->wp->query_vars['p'] ) ) : null;
		} elseif ( isset( $this->wp->query_vars['name'] ) ) {

			// Target post types with a public URI.
			$allowed_post_types = get_post_types( [
				'show_in_graphql' => true,
				'public'          => true,
			] );

			$post_type = 'post';
			if ( isset( $this->wp->query_vars['post_type'] ) && in_array( $this->wp->query_vars['post_type'], $allowed_post_types, true ) ) {
				$post_type = $this->wp->query_vars['post_type'];
			}

			// @phpstan-ignore-next-line
			$post = get_page_by_path( $this->wp->query_vars['name'], 'OBJECT', $post_type );

			unset( $this->wp->query_vars['uri'] );
			$post = $post instanceof WP_Post ? $this->validate_post( $post ) : null;

			return isset( $post->ID ) ? $this->context->get_loader( 'post' )->load_deferred( $post->ID ) : null;

		} elseif ( isset( $this->wp->query_vars['cat'] ) ) {
			$node = get_term( absint( $this->wp->query_vars['cat'] ), 'category' );

			return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( (int) $node->term_id ) : null;

		} elseif ( isset( $this->wp->query_vars['tag'] ) ) {
			$node = get_term_by( 'slug', $this->wp->query_vars['tag'], 'post_tag' );

			return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( (int) $node->term_id ) : null;
		} elseif ( isset( $this->wp->query_vars['pagename'] ) && ! empty( $this->wp->query_vars['pagename'] ) ) {

			unset( $this->wp->query_vars['uri'] );

			$post_type = isset( $this->wp->query_vars['post_type'] ) ? $this->wp->query_vars['post_type'] : get_post_types( [ 'show_in_graphql' => true ] );

			$post = get_page_by_path( $this->wp->query_vars['pagename'], 'OBJECT', $post_type );

			if ( ! $post instanceof WP_Post ) {
				return null;
			}

			$post = $this->validate_post( $post );

			if ( ! $post ) {
				return null;
			}

			if ( get_option( 'page_for_posts', 0 ) === $post->ID ) {
				return $this->context->get_loader( 'post' )->load_deferred( $post->ID );
			}

			return $this->context->get_loader( 'post' )->load_deferred( $post->ID );
		} elseif ( isset( $this->wp->query_vars['author_name'] ) ) {
			$user = get_user_by( 'slug', $this->wp->query_vars['author_name'] );

			return isset( $user->ID ) ? $this->context->get_loader( 'user' )->load_deferred( $user->ID ) : null;
		} elseif ( isset( $this->wp->query_vars['category_name'] ) ) {
			$term = get_category_by_path( $this->wp->query_vars['category_name'] );
			if ( ! $term instanceof \WP_Term ) {
				return null;
			}
			$node = get_term_by( 'id', $term->term_id, 'category' );
			return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( $node->term_id ) : null;

		} elseif ( isset( $this->wp->query_vars['post_type'] ) ) {

			// If the query is asking for a Page nodeType with the home uri, try and resolve it.
			if ( '/' === $this->wp->query_vars['uri'] && ( isset( $this->wp->query_vars['nodeType'] ) && 'Page' === $this->wp->query_vars['nodeType'] ) ) {

				// If the post type is not a page, but the uri is for the home page, we can return null now
				if ( 'page' !== $this->wp->query_vars['post_type'] ) {
					return null;
				}

				$page_on_front = get_option( 'page_on_front', 0 );
				$post          = get_post( absint( $page_on_front ) );
				return ! empty( $post ) ? $this->context->get_loader( 'post' )->load_deferred( $post->ID ) : null;
			}

			if ( isset( $this->wp->query_vars['page'], $this->wp->query_vars['uri'] ) ) {
				$post_type = $this->wp->query_vars['post_type'];

				$post = get_page_by_path( $this->wp->query_vars['uri'], 'OBJECT', $post_type );

				$post = isset( $post->ID ) ? $this->validate_post( $post ) : null;
				return isset( $post->ID ) ? $this->context->get_loader( 'post' )->load_deferred( $post->ID ) : null;
			}

			$post_type_object = get_post_type_object( $this->wp->query_vars['post_type'] );

			return ! empty( $post_type_object ) ? $this->context->get_loader( 'post_type' )->load_deferred( $post_type_object->name ) : null;
		} else {
			$taxonomies = get_taxonomies( [ 'show_in_graphql' => true ], 'objects' );
			foreach ( $taxonomies as $taxonomy ) {
				if ( isset( $this->wp->query_vars[ $taxonomy->query_var ] ) ) {
					$node = get_term_by( 'slug', $this->wp->query_vars[ $taxonomy->query_var ], $taxonomy->name );

					return isset( $node->term_id ) ? $this->context->get_loader( 'term' )->load_deferred( $node->term_id ) : null;
				}
			}
		}

		return $node;
	}
}
