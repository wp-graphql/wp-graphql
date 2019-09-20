<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

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
 *
 * @package WPGraphQL\Model
 */
class User extends Model {

	/**
	 * Stores the WP_User object for the incoming data
	 *
	 * @var \WP_User $data
	 * @access protected
	 */
	protected $data;

	/**
	 * User constructor.
	 *
	 * @param \WP_User $user The incoming WP_User object that needs modeling
	 *
	 * @access public
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_User $user ) {

		// Explicitly remove the user_pass early on so it doesn't show up in filters/hooks
		$user->user_pass = null;
		$this->data      = $user;

		$allowed_restricted_fields = [
			'isRestricted',
			'isPrivate',
			'isPublic',
			'id',
			'userId',
			'name',
			'firstName',
			'lastName',
			'description',
			'slug',
		];

		parent::__construct( 'list_users', $allowed_restricted_fields, $user->ID );

	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @access protected
	 * @return bool
	 */
	protected function is_private() {

		/**
		 * Filter whether the user is private.
		 *
		 * If true, the user will be considered private without further checks.
		 *
		 * @param mixed|null|boolean $is_private If set, return without executing the manual query
		 *                                       to see if the user has published posts.
		 * @param User               $this       Instance of the User Model
		 */
		$is_private = apply_filters( 'graphql_user_model_is_private', null, $this );

		/**
		 * If the filter returns true, we can return now as the user is considered private.
		 *
		 * Otherwise we need to continue and check if the User should be considered private or not
		 */
		if ( null !== $is_private ) {
			return (bool) $is_private;
		}

		/**
		 * If the requesting user does not have "list_users" capabilities and the current user is not
		 * the user being requested, we need to check if the user being requested has published posts
		 * which makes them a non-private entity.
		 */
		if ( ! current_user_can( 'list_users' ) && false === $this->owner_matches_current_user() ) {

			/**
			 * Get allowed Post Types
			 */
			$post_types = \WPGraphQL::get_allowed_post_types();
			unset( $post_types['revision'] );
			unset( $post_types['attachment'] );

			/**
			 * If the user has no published posts of any allowed post type,
			 * they are considered to be private entities.
			 */
			if ( 0 === $this->count_user_posts( absint( $this->data->ID ), $post_types, true ) ) {
				$is_private = true;
			}
		}

		return $is_private;

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
	 * @param int @userid ID of the User
	 * @param mixed|string|array         $post_type   The post type(s) to check for published posts in
	 * @param boolean                    $public_only Whether to count only publicly published posts
	 *
	 * @return int|mixed|boolean
	 */
	protected function count_user_posts( $userid, $post_type = 'post', $public_only = false ) {

		if ( ! is_numeric( $userid ) ) {
			return 0;
		}

		$cache_key   = 'graphql_user_' . (int) $userid;
		$cache_group = 'user_posts_count';

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
		if ( false === ( $count = wp_cache_get( $cache_key, $cache_group ) ) ) {
			global $wpdb;

			$where   = get_posts_by_author_sql( $post_type, true, $userid, $public_only );
			$results = $wpdb->get_results( "SELECT id FROM $wpdb->posts $where LIMIT 1" );

			$count = $results ? count( $results ) : 0;
		}

		/**
		 * Filters the number of posts a user has written.
		 *
		 * @param int          $count       The user's post count.
		 * @param int          $userid      User ID.
		 * @param string|array $post_type   Single post type or array of post types to count the number of posts for.
		 * @param bool         $public_only Whether to limit counted posts to public posts.
		 */
		return (int) apply_filters( 'graphql_count_user_posts', $count, $userid, $post_type, $public_only );

	}

	/**
	 * Initialize the User object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id'                => function() {
					return ( ! empty( $this->data->ID ) ) ? Relay::toGlobalId( 'user', $this->data->ID ) : null;
				},
				'capabilities'      => function() {
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
				'capKey'            => function() {
					return ! empty( $this->data->cap_key ) ? $this->data->cap_key : null;
				},
				'roles'             => function() {
					return ! empty( $this->data->roles ) ? $this->data->roles : null;
				},
				'email'             => function() {
					return ! empty( $this->data->user_email ) ? $this->data->user_email : null;
				},
				'firstName'         => function() {
					return ! empty( $this->data->first_name ) ? $this->data->first_name : null;
				},
				'lastName'          => function() {
					return ! empty( $this->data->last_name ) ? $this->data->last_name : null;
				},
				'extraCapabilities' => function() {
					return ! empty( $this->data->allcaps ) ? array_keys( $this->data->allcaps ) : null;
				},
				'description'       => function() {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'username'          => function() {
					return ! empty( $this->data->user_login ) ? $this->data->user_login : null;
				},
				'name'              => function() {
					return ! empty( $this->data->display_name ) ? $this->data->display_name : null;
				},
				'registeredDate'    => function() {
					return ! empty( $this->data->user_registered ) ? date( 'c', strtotime( $this->data->user_registered ) ) : null;
				},
				'nickname'          => function() {
					return ! empty( $this->data->nickname ) ? $this->data->nickname : null;
				},
				'url'               => function() {
					return ! empty( $this->data->user_url ) ? $this->data->user_url : null;
				},
				'slug'              => function() {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'nicename'          => function() {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'locale'            => function() {
					$user_locale = get_user_locale( $this->data );

					return ! empty( $user_locale ) ? $user_locale : null;
				},
				'userId'            => ! empty( $this->data->ID ) ? absint( $this->data->ID ) : null,
			];

		}

	}

}
