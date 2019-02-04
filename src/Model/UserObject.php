<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class UserObject extends Model {

	protected $user;

	protected $fields;

	public function __construct( \WP_User $user ) {

		if ( empty( $user ) ) {
			return;
		}

		$this->user = $user;

		$allowed_restricted_fields = [
			'isRestricted',
			'id',
			'url',
		];

		parent::__construct( 'UserObject', $user, 'list_users', $allowed_restricted_fields, $user->ID );

	}

	public function get_instance( $fields = null ) {

		if ( 'private' === $this->get_visibility() ) {
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
				'userId' => function() {
					return ! empty( $this->user->ID ) ? absint( $this->user->ID ) : null;
				},
			];
		}

		return parent::return_instance( $this->fields, $fields );

	}

}
