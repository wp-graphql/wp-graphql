<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;
use WP_User;

/**
 * Class User - Models the data for the User object type
 *
 * @property string[]|null $capabilities
 * @property ?string       $capKey
 * @property ?int          $databaseId
 * @property ?string       $description
 * @property ?string       $email
 * @property string[]      $enqueuedScriptsQueue
 * @property string[]      $enqueuedStylesheetsQueue
 * @property string[]|null $extraCapabilities
 * @property ?string       $firstName
 * @property ?string       $id
 * @property ?string       $lastName
 * @property ?string       $locale
 * @property ?string       $name
 * @property ?string       $nicename
 * @property ?string       $nickname
 * @property ?string       $registeredDate
 * @property string[]|null $roles
 * @property ?string       $slug
 * @property string        $uri
 * @property ?string       $url
 * @property ?string       $username
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_User>
 */
class User extends Model {
	/**
	 * The Global Post at time of Model generation
	 *
	 * @var \WP_Post
	 */
	protected $global_post;

	/**
	 * The global authordata at time of Model generation
	 *
	 * @var \WP_User
	 */
	protected $global_authordata;

	/**
	 * User constructor.
	 *
	 * @param \WP_User $user The incoming WP_User object that needs modeling
	 *
	 * @return void
	 */
	public function __construct( WP_User $user ) {

		// Explicitly remove the user_pass early on so it doesn't show up in filters/hooks
		$user->user_pass = '';
		$this->data      = $user;

		$allowed_restricted_fields = [
			'databaseId',
			'description',
			'enqueuedScriptsQueue',
			'enqueuedStylesheetsQueue',
			'firstName',
			'id',
			'isRestricted',
			'lastName',
			'name',
			'slug',
			'uri',
			'url',
			'userId',
		];

		parent::__construct( 'list_users', $allowed_restricted_fields, $user->ID );
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	public function tear_down() {
		$GLOBALS['authordata'] = $this->global_authordata; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		$GLOBALS['post']       = $this->global_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		wp_reset_postdata();
	}

	/**
	 * {@inheritDoc}
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
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'capabilities'             => function () {
					if ( ! empty( $this->data->allcaps ) ) {

						/**
						 * Reformat the array of capabilities from the user object so that it is a true
						 * ListOf type
						 */
						$capabilities = array_keys(
							array_filter(
								$this->data->allcaps,
								static function ( $cap ) {
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
				'databaseId'               => function () {
					return ! empty( $this->data->ID ) ? absint( $this->data->ID ) : null;
				},
				'description'              => function () {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'email'                    => function () {
					return ! empty( $this->data->user_email ) ? $this->data->user_email : null;
				},
				'enqueuedScriptsQueue'     => static function () {
					global $wp_scripts;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_scripts->queue;
					$wp_scripts->reset();
					$wp_scripts->queue = [];

					return $queue;
				},
				'enqueuedStylesheetsQueue' => static function () {
					global $wp_styles;
					do_action( 'wp_enqueue_scripts' );
					$queue = $wp_styles->queue;
					$wp_styles->reset();
					$wp_styles->queue = [];

					return $queue;
				},
				'extraCapabilities'        => function () {
					return ! empty( $this->data->allcaps ) ? array_keys( $this->data->allcaps ) : null;
				},
				'firstName'                => function () {
					return ! empty( $this->data->first_name ) ? $this->data->first_name : null;
				},
				'id'                       => function () {
					return ( ! empty( $this->data->ID ) ) ? Relay::toGlobalId( 'user', (string) $this->data->ID ) : null;
				},
				'lastName'                 => function () {
					return ! empty( $this->data->last_name ) ? $this->data->last_name : null;
				},
				'locale'                   => function () {
					$user_locale = get_user_locale( $this->data );

					return ! empty( $user_locale ) ? $user_locale : null;
				},
				'nicename'                 => function () {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'name'                     => function () {
					return ! empty( $this->data->display_name ) ? $this->data->display_name : null;
				},
				'nickname'                 => function () {
					return ! empty( $this->data->nickname ) ? $this->data->nickname : null;
				},
				'registeredDate'           => function () {
					$timestamp = ! empty( $this->data->user_registered ) ? strtotime( $this->data->user_registered ) : null;
					return ! empty( $timestamp ) ? gmdate( 'c', $timestamp ) : null;
				},
				'roles'                    => function () {
					return ! empty( $this->data->roles ) ? $this->data->roles : null;
				},
				'shouldShowAdminToolbar'   => function () {
					$toolbar_preference_meta = get_user_meta( $this->data->ID, 'show_admin_bar_front', true );

					return 'true' === $toolbar_preference_meta;
				},
				'slug'                     => function () {
					return ! empty( $this->data->user_nicename ) ? $this->data->user_nicename : null;
				},
				'uri'                      => function () {
					$user_profile_url = get_author_posts_url( $this->data->ID );

					return ! empty( $user_profile_url ) ? str_ireplace( home_url(), '', $user_profile_url ) : '';
				},
				'url'                      => function () {
					return ! empty( $this->data->user_url ) ? $this->data->user_url : null;
				},
				'username'                 => function () {
					return ! empty( $this->data->user_login ) ? $this->data->user_login : null;
				},

				// Deprecated.
				'userId'                   => function () {
					return $this->databaseId;
				},
			];
		}
	}
}
