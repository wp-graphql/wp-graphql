<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class UserObject extends Model {

	/**
	 * We should only return the UserObject if the current user has the list_users cap
	 */

	protected  $user;

	protected $current_user_id;

	protected $visibility;

	protected $allowed_restricted_fields;

	public function __construct( \WP_User $user ) {

		if ( empty( $user ) ) {
			return;
		}

		$this->user = $user;

		parent::__construct();

	}

	protected function get_visibility() {

		/**
		 * @TODO: decide on naming conventions for visibility nouns
		 */

		if ( null === $this->visibility ) {
			$protected_cap = apply_filters( 'wp_graphql_model_private_cap', 'list_users' );

			if ( $this->user_matches() ) {
				$this->visibility = 'public';
			} else if ( current_user_can( $protected_cap ) ) {
				$this->visibility = 'public';
			} else {
				$this->visibility = 'protected';
			}

		}

		return $this->visibility;

	}

	protected function user_matches() {
		return ( $this->user->ID === $this->current_user->ID ) ? true : false;
	}

	protected function get_allowed_restricted_fields() {

		if ( null === $this->allowed_restricted_fields ) {
			$this->allowed_restricted_fields = [
				'isRestricted',
				'id',
				'url',
			];
		}

		return apply_filters( 'graphql_restricted_user_allowed_fields', $this->allowed_restricted_fields );

	}

	protected function restrict_fields( $fields ) {
		return array_intersect_key( $fields, array_flip( $this->get_allowed_restricted_fields() ) );
	}

	public function get_instance() {

		if ( 'private' === $this->get_visibility() ) {
			return null;
		}

		$user_fields = [
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
			'isRestricted' => function() {
				return ( 'protected' === $this->get_visibility() ) ? true : false;
			},
			'avatar' => function() {
			/**
			 * @TODO: figure out what to do with this...
			 */
//				$avatar_args = [];
//				if ( is_numeric( $args['size'] ) ) {
//					$avatar_args['size'] = absint( $args['size'] );
//					if ( ! $avatar_args['size'] ) {
//						$avatar_args['size'] = 96;
//					}
//				}
//
//				if ( ! empty( $args['forceDefault'] ) && true === $args['forceDefault'] ) {
//					$avatar_args['force_default'] = true;
//				}
//
//				if ( ! empty( $args['rating'] ) ) {
//					$avatar_args['rating'] = esc_sql( $args['rating'] );
//				}
//
//				$avatar = get_avatar_data( $this->user->ID, $avatar_args );
//
//				return ( ! empty( $avatar ) && true === $avatar['found_avatar'] ) ? $avatar : null;
			}

		];

		if ( 'protected' === $this->get_visibility() ) {
			$user_fields = $this->restrict_fields( $user_fields );
		}

		return parent::prepare_object( $user_fields, 'user', 'WP_User' );

	}

}
