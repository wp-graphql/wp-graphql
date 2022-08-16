<?php

namespace WPGraphQL\Model;

use Exception;
use GraphQLRelay\Relay;
use WP_Post;
use WP_User;

/**
 * Class User - Models the data for the User object type
 *
 * @property string $id
 * @property int    $databaseId
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
			'databaseId',
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

		if ( $this->data instanceof WP_User ) {

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
			$GLOBALS['authordata']       = $this->data; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
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
		$GLOBALS['authordata'] = $this->global_authordata; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['post']       = $this->global_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_reset_postdata();
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @return bool
	 */
	protected function is_private() {
		/**
		 * If the user has permissions to list users.
		 */
		if ( current_user_can( $this->restricted_cap ) ) {
			return false;
		}

		/**
		 * If the owner of the content is the current user
		 */
		if ( true === $this->owner_matches_current_user() ) {
			return false;
		}

		return $this->data->is_private ?? true;
	}

	/**
	 * Initialize the User object
	 *
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id'                       => function () {
					return ( ! empty( $this->data->ID ) ) ? Relay::toGlobalId( 'user', (string) $this->data->ID ) : null;
				},
				'databaseId'               => function () {
					return $this->userId;
				},
				'capabilities'             => function () {
					if ( ! empty( $this->data->allcaps ) ) {

						/**
						 * Reformat the array of capabilities from the user object so that it is a true
						 * ListOf type
						 */
						$capabilities = array_keys(
							array_filter(
								$this->data->allcaps,
								function ( $cap ) {
									return true === $cap;
								}
							)
						);

					}

					return ! empty( $capabilities ) ? $capabilities : null;

				},
				'capKey'                   => function () {
					return ! empty( $this->data->cap_key ) ? $this->data->cap_key : null;
				},
				'roles'                    => function () {
					return ! empty( $this->data->roles ) ? $this->data->roles : null;
				},
				'email'                    => function () {
					return ! empty( $this->data->user_email ) ? $this->data->user_email : null;
				},
				'firstName'                => function () {
					return ! empty( $this->data->first_name ) ? $this->data->first_name : null;
				},
				'lastName'                 => function () {
					return ! empty( $this->data->last_name ) ? $this->data->last_name : null;
				},
				'extraCapabilities'        => function () {
					return ! empty( $this->data->allcaps ) ? array_keys( $this->data->allcaps ) : null;
				},
				'description'              => function () {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'username'                 => function () {
					return ! empty( $this->data->user_login ) ? $this->data->user_login : null;
				},
				'name'                     => function () {
					return ! empty( $this->data->display_name ) ? $this->data->display_name : null;
				},
				'registeredDate'           => function () {
					$timestamp = ! empty( $this->data->user_registered ) ? strtotime( $this->data->user_registered ) : null;
					return ! empty( $timestamp ) ? gmdate( 'c', $timestamp ) : null;
				},
				'nickname'                 => function () {
					return ! empty( $this->data->nickname ) ? $this->data->nickname : null;
				},
				'url'                      => function () {
					return ! empty( $this->data->user_url ) ? $this->data->user_url : null;
				},
				'slug'                     => function () {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'nicename'                 => function () {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'locale'                   => function () {
					$user_locale = get_user_locale( $this->data );

					return ! empty( $user_locale ) ? $user_locale : null;
				},
				'userId'                   => ! empty( $this->data->ID ) ? absint( $this->data->ID ) : null,
				'uri'                      => function () {
					$user_profile_url = get_author_posts_url( $this->data->ID );

					return ! empty( $user_profile_url ) ? str_ireplace( home_url(), '', $user_profile_url ) : '';
				},
				'enqueuedScriptsQueue'     => function () {
					global $wp_scripts;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_scripts->queue;
					$wp_scripts->reset();
					$wp_scripts->queue = [];

					return $queue;
				},
				'enqueuedStylesheetsQueue' => function () {
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

}
