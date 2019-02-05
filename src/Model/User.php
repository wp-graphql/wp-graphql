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
	 * @var \WP_User $user
	 * @access protected
	 */
	protected $user;

	/**
	 * Stores the fields for the User model
	 *
	 * @var array $fields
	 * @access public
	 */
	public $fields = [];

	/**
	 * User constructor.
	 *
	 * @param \WP_User $user The incoming WP_User object that needs modeling
	 *
	 * @access public
	 * @return void
	 */
	public function __construct( \WP_User $user ) {

		if ( empty( $user ) ) {
			return;
		}

		// Explicitly remove the user_pass early on so it doesn't show up in filters/hooks
		$user->user_pass = null;
		$this->user = $user;

		$allowed_restricted_fields = [
			'isRestricted',
			'isPrivate',
			'isPublic',
			'id',
			'userId',
			'name',
			'description',
			'slug',
		];

		parent::__construct( 'UserObject', $user, 'list_users', $allowed_restricted_fields, $user->ID );

	}

	/**
	 * Initialize the User object
	 *
	 * @param null|string|array $filter The field or fields to build in the modeled object. You can
	 *                                  pass null to build all of the fields, a string to only
	 *                                  build an object with one field, or an array of field keys
	 *                                  to build an object with those keys and their respective
	 *                                  values.
	 *
	 * @access public
	 * @return void
	 */
	public function init( $filter = null ) {

		if ( 'private' === $this->get_visibility() || is_null( $this->user ) ) {
			return null;
		}

		if ( null == $this->fields ) {
			$this->fields = [
				'id' => function() {
					return ( ! empty( $this->user->ID ) ) ? Relay::toGlobalId( 'user', $this->user->ID ) : null;
				},
				'capabilities' => function() {
					if ( ! empty( $this->user->allcaps ) ) {

						/**
						 * Reformat the array of capabilities from the user object so that it is a true
						 * ListOf type
						 */
						$capabilities = array_keys( array_filter( $this->user->allcaps, function( $cap ) {
							return true === $cap;
						} ) );

					}

					return ! empty( $capabilities ) ? $capabilities : null;

				},
				'capKey' => function() {
					return ! empty( $this->user->cap_key ) ? $this->user->cap_key : null;
				},
				'roles' => function() {
					return ! empty( $this->user->roles ) ? $this->user->roles : null;
				},
				'email' => function() {
					return ! empty( $this->user->user_email ) ? $this->user->user_email : null;
				},
				'firstName' => function() {
					return ! empty( $this->user->first_name ) ? $this->user->first_name : null;
				},
				'lastName' => function() {
					return ! empty( $this->user->last_name ) ? $this->user->last_name : null;
				},
				'extraCapabilities' => function() {
					return ! empty( $this->user->allcaps ) ? array_keys( $this->user->allcaps ) : null;
				},
				'description' => function() {
					return ! empty( $this->user->description ) ? $this->user->description : null;
				},
				'username' => function() {
					return ! empty( $this->user->user_login ) ? $this->user->user_login : null;
				},
				'name' => function() {
					return ! empty( $this->user->display_name ) ? $this->user->display_name : null;
				},
				'registeredDate' => function() {
					return ! empty( $this->user->user_registered ) ? date( 'c', strtotime( $this->user->user_registered ) ) : null;
				},
				'nickname' => function() {
					return ! empty( $this->user->nickname ) ? $this->user->nickname : null;
				},
				'url' => function() {
					return ! empty( $this->user->user_url ) ? $this->user->user_url : null;
				},
				'slug' => function() {
					return ! empty( $this->user->user_nicename ) ? $this->user->user_nicename : null;
				},
				'nicename' => function() {
					return ! empty( $this->user->user_nicename ) ? $this->user->user_nicename : null;
				},
				'locale' => function() {
					$user_locale = get_user_locale( $this->user );
					return ! empty( $user_locale ) ? $user_locale : null;
				},
				'userId' => ! empty( $this->user->ID ) ? absint( $this->user->ID ) : null,
			];
		}

		$this->fields = parent::prepare_fields( $this->fields, $filter );

	}

}
