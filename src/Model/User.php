<?php

namespace WPGraphQL\Model;

use Exception;
use GraphQLRelay\Relay;
use WP_Post;
use WP_User;
use WPGraphQL;

/**
 * Class User - Models the data for the User object type
 *
 * @property string $id
 * @property array  $capabilities
 * @property string $capKey
 * @property array  $roles
 * @property string $email
 * @property string $firstName
 * @property string $lastName
 * @property array  $extraCapabilities
 * @property string $description
 * @property string $username
 * @property string $name
 * @property string $registeredDate
 * @property string $nickname
 * @property string $url
 * @property string $slug
 * @property string $nicename
 * @property string $locale
 * @property int    $userId
 * @property string $uri
 * @property string $enqueuedScriptsQueue
 * @property string $enqueuedStylesheetsQueue
 *
 * @package WPGraphQL\Model
 */
class User extends Model {

	/**
	 * Stores the WP_User object for the incoming data
	 *
	 * @var WP_User $data
	 */
	protected $data;

	/**
	 * The Global Post at time of Model generation
	 *
	 * @var WP_Post
	 */
	protected $global_post;

	/**
	 * The global authordata at time of Model generation
	 *
	 * @var WP_User
	 */
	protected $global_authordata;

	/**
	 * User constructor.
	 *
	 * @param WP_User $user The incoming WP_User object that needs modeling
	 *
	 * @return void
	 * @throws Exception
	 */
	public function __construct( WP_User $user ) {

		// Explicitly remove the user_pass early on so it doesn't show up in filters/hooks
		$user->user_pass = '';
		$this->data      = $user;

		$allowed_restricted_fields = [
			'isRestricted',
			'id',
			'userId',
			'name',
			'firstName',
			'lastName',
			'description',
			'slug',
			'uri',
			'enqueuedScriptsQueue',
			'enqueuedStylesheetsQueue',
		];

		parent::__construct( 'list_users', $allowed_restricted_fields, $user->ID );

		add_action( 'save_post', [ $this, 'flush_user_post_count_cache' ], 10, 2 );

	}

	/**
	 * Setup the global data for the model to have proper context when resolving
	 *
	 * @return void
	 */
	public function setup() {

		global $wp_query, $post, $authordata;

		// Store variables for resetting at tear down
		$this->global_post       = $post;
		$this->global_authordata = $authordata;

		if ( ! empty( $this->data ) ) {

			// Reset postdata
			$wp_query->reset_postdata();

			// Parse the query to setup global state
			$wp_query->parse_query(
				[
					'author_name' => $this->data->user_nicename,
				]
			);

			// Setup globals
			$wp_query->is_author         = true;
			$GLOBALS['authordata']       = $this->data;
			$wp_query->queried_object    = get_user_by( 'id', $this->data->ID );
			$wp_query->queried_object_id = $this->data->ID;
		}

	}

	/**
	 * Reset global state after the model fields
	 * have been generated
	 *
	 * @return void
	 */
	public function tear_down() {
		$GLOBALS['authordata'] = $this->global_authordata;
		$GLOBALS['post']       = $this->global_post;
		wp_reset_postdata();
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @return bool
	 */
	protected function is_private() {

		if ( ! current_user_can( 'list_users' ) && false === $this->owner_matches_current_user() ) {

			/**
			 * @todo: We should handle this check in a Deferred resolver. Right now it queries once per user
			 *      but we _could_ query once for _all_ users.
			 *
			 *      For now, we only query if the current user doesn't have list_users, instead of querying
			 *      for ALL users. Slightly more efficient for authenticated users at least.
			 */
			if ( ! $this->count_user_posts( absint( $this->data->ID ), WPGraphQL::get_allowed_post_types(), true ) ) {
				return true;
			}
		}

		return false;

	}

	/**
	 * Initialize the User object
	 *
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id'                       => function() {
					return ( ! empty( $this->data->ID ) ) ? Relay::toGlobalId( 'user', (string) $this->data->ID ) : null;
				},
				'capabilities'             => function() {
					if ( ! empty( $this->data->allcaps ) ) {

						/**
						 * Reformat the array of capabilities from the user object so that it is a true
						 * ListOf type
						 */
						$capabilities = array_keys(
							array_filter(
								$this->data->allcaps,
								function( $cap ) {
									return true === $cap;
								}
							)
						);

					}

					return ! empty( $capabilities ) ? $capabilities : null;

				},
				'capKey'                   => function() {
					return ! empty( $this->data->cap_key ) ? $this->data->cap_key : null;
				},
				'roles'                    => function() {
					return ! empty( $this->data->roles ) ? $this->data->roles : null;
				},
				'email'                    => function() {
					return ! empty( $this->data->user_email ) ? $this->data->user_email : null;
				},
				'firstName'                => function() {
					return ! empty( $this->data->first_name ) ? $this->data->first_name : null;
				},
				'lastName'                 => function() {
					return ! empty( $this->data->last_name ) ? $this->data->last_name : null;
				},
				'extraCapabilities'        => function() {
					return ! empty( $this->data->allcaps ) ? array_keys( $this->data->allcaps ) : null;
				},
				'description'              => function() {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'username'                 => function() {
					return ! empty( $this->data->user_login ) ? $this->data->user_login : null;
				},
				'name'                     => function() {
					return ! empty( $this->data->display_name ) ? $this->data->display_name : null;
				},
				'registeredDate'           => function() {
					$timestamp = ! empty( $this->data->user_registered ) ? strtotime( $this->data->user_registered ) : null;
					return ! empty( $timestamp ) ? gmdate( 'c', $timestamp ) : null;
				},
				'nickname'                 => function() {
					return ! empty( $this->data->nickname ) ? $this->data->nickname : null;
				},
				'url'                      => function() {
					return ! empty( $this->data->user_url ) ? $this->data->user_url : null;
				},
				'slug'                     => function() {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'nicename'                 => function() {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'locale'                   => function() {
					$user_locale = get_user_locale( $this->data );

					return ! empty( $user_locale ) ? $user_locale : null;
				},
				'userId'                   => ! empty( $this->data->ID ) ? absint( $this->data->ID ) : null,
				'uri'                      => function() {
					$user_profile_url = get_author_posts_url( $this->data->ID );

					return ! empty( $user_profile_url ) ? str_ireplace( home_url(), '', $user_profile_url ) : '';
				},
				'enqueuedScriptsQueue'     => function() {
					global $wp_scripts;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_scripts->queue;
					$wp_scripts->reset();
					$wp_scripts->queue = [];

					return $queue;
				},
				'enqueuedStylesheetsQueue' => function() {
					global $wp_styles;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_styles->queue;
					$wp_styles->reset();
					$wp_styles->queue = [];

					return $queue;
				},
			];

		}

	}

	/**
	 * Cached version (and variation) of count_user_posts, which is uncached but doesn't always
	 * need to hit the db
	 *
	 * count_user_posts is generally fast on smaller sites, but slows on large data sets.
	 *
	 * It can also be easy to end up with many redundant queries if it's called several times per
	 * request. This allows bypassing the db queries in favor of the cache
	 *
	 * @param int                $userid      ID of the User
	 * @param mixed|string|array $post_type   The post type(s) to check for published posts in
	 * @param boolean            $public_only Whether to count only publicly published posts
	 *
	 * @return int|mixed|boolean
	 */
	protected function count_user_posts( $userid, $post_type = 'post', $public_only = false ) {

		if ( ! is_numeric( $userid ) ) {
			return 0;
		}

		$cache_key       = 'graphql_user_' . (int) $userid;
		$cache_group     = 'user_posts_count';
		$user_post_count = wp_cache_get( $cache_key, $cache_group );

		/**
		 * If there's no cache, query to see if the author has 1 published post.
		 *
		 * This method of querying is more efficient on large datasets
		 * then the core count_user_posts() method as this stops at the first match,
		 * where the count_user_psts() method needs to find all matches and
		 * it can take a while to calculate.
		 *
		 * We only care if there's at least 1 published post, not the total.
		 */
		if ( false === $user_post_count ) {
			global $wpdb;

			$where           = get_posts_by_author_sql( $post_type, true, $userid, $public_only );
			$results         = $wpdb->get_results( "SELECT id FROM $wpdb->posts $where LIMIT 1" );
			$user_post_count = $results ? count( $results ) : 0;

			wp_cache_set( $cache_key, $user_post_count, $cache_group, 5 * MINUTE_IN_SECONDS ); // Cache for 5 mins.
		}

		/**
		 * Filters the number of posts a user has written.
		 *
		 * @param int          $user_post_count The user's post count.
		 * @param int          $userid          User ID.
		 * @param string|array $post_type       Single post type or array of post types to count the number of posts for.
		 * @param bool         $public_only     Whether to limit counted posts to public posts.
		 */
		return (int) apply_filters( 'graphql_count_user_posts', $user_post_count, $userid, $post_type, $public_only );

	}

	/**
	 * Function to flush user post count cache.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 *
	 * @return void
	 */
	public function flush_user_post_count_cache( $post_ID, $post ) {

		// Check post author is empty.
		if ( empty( $post ) || empty( $post->post_author ) ) {
			return;
		}

		$cache_key   = 'graphql_user_' . (int) $post->post_author;
		$cache_group = 'user_posts_count';

		wp_cache_delete( $cache_key, $cache_group );

	}

}
