<?php

namespace WPGraphQL\Type\User\Mutation;

use WPGraphQL\Types;

/**
 * Class UserMutation
 *
 * @package WPGraphQL\Type\User\Mutation
 */
class UserMutation {

	/**
	 * Stores the input fields static definition
	 *
	 * @var array $input_fields
	 * @access private
	 */
	private static $input_fields = [];

	/**
	 * Defines the accepted input arguments
	 *
	 * @return array|null
	 * @access public
	 */
	public static function input_fields() {

		if ( empty( self::$input_fields ) ) {

			$input_fields = [
				'password'    => [
					'type'        => Types::string(),
					'description' => __( 'A string that contains the plain text password for the user.', 'wp-graphql' ),
				],
				'login'       => [
					'type'        => Types::string(),
					'description' => __( 'A string that contains the user\'s username for logging in.', 'wp-graphql' ),
				],
				'nicename'    => [
					'type'        => Types::string(),
					'description' => __( 'A string that contains a URL-friendly name for the user. The default is the user\'s username.', 'wp-graphql' ),
				],
				'websiteUrl'  => [
					'type'        => Types::string(),
					'description' => __( 'A string containing the user\'s URL for the user\'s web site.', 'wp-grapql' ),
				],
				'email'       => [
					'type'        => Types::string(),
					'description' => __( 'A string containing the user\'s email address.', 'wp-graphql' ),
				],
				'displayName' => [
					'type'        => Types::string(),
					'description' => __( 'A string that will be shown on the site. Defaults to user\'s username. It is likely that you will want to change this, for both appearance and security through obscurity (that is if you dont use and delete the default admin user).', 'wp-graphql' ),
				],
				'nickname'    => [
					'type'        => Types::string(),
					'description' => __( 'The user\'s nickname, defaults to the user\'s username.', 'wp-graphql' ),
				],
				'firstName'   => [
					'type'        => Types::string(),
					'description' => __( '	The user\'s first name.', 'wp-graphql' ),
				],
				'lastName'    => [
					'type'        => Types::string(),
					'description' => __( 'The user\'s last name.', 'wp-graphql' ),
				],
				'description' => [
					'type'        => Types::string(),
					'description' => __( 'A string containing content about the user.', 'wp-graphql' ),
				],
				'richEditing' => [
					'type'        => Types::string(),
					'description' => __( 'A string for whether to enable the rich editor or not. False if not empty.', 'wp-graphql' ),
				],
				'registered'  => [
					'type'        => Types::string(),
					'description' => __( 'The date the user registered. Format is Y-m-d H:i:s.', 'wp-graphql' ),
				],
				'role'        => [
					'type'        => Types::string(),
					'description' => __( 'A string used to set the user\'s role.', 'wp-graphql' ),
				],
				'jabber'      => [
					'type'        => Types::string(),
					'description' => __( 'User\'s Jabber account.', 'wp-graphql' ),
				],
				'aim'         => [
					'type'        => Types::string(),
					'description' => __( 'User\'s AOL IM account.', 'wp-graphql' ),
				],
				'yim'         => [
					'type'        => Types::string(),
					'description' => __( 'User\'s Yahoo IM account.', 'wp-graphql' ),
				],
				'locale'      => [
					'type'        => Types::string(),
					'description' => __( 'User\'s locale.', 'wp-graphql' ),
				],
			];

			/**
			 * Filters all of the fields available for input
			 *
			 * @var array $input_fields
			 */
			self::$input_fields = apply_filters( 'graphql_user_mutation_input_fields', $input_fields );

		}

		return ( ! empty( self::$input_fields ) ) ? self::$input_fields : null;

	}

	/**
	 * Maps the GraphQL input to a format that the WordPress functions can use
	 *
	 * @param array $input Data coming from the GraphQL mutation query input
	 * @param string $mutation_name Name of the mutation being performed
	 * @access public
	 * @return array
	 */
	public static function prepare_user_object( $input, $mutation_name ) {

		$insert_user_args = [];

		if ( ! empty( $input['password'] ) ) {
			$insert_user_args['user_pass'] = $input['password'];
		}

		if ( ! empty( $input['login'] ) ) {
			$insert_user_args['user_login'] = $input['login'];
		}

		if ( ! empty( $input['nicename'] ) ) {
			$insert_user_args['user_nicename'] = $input['nicename'];
		}

		if ( ! empty( $input['websiteUrl'] ) ) {
			$insert_user_args['user_url'] = esc_url( $input['websiteUrl'] );
		}

		if ( ! empty( $input['email'] ) ) {
			$insert_user_args['user_email'] = $input['email'];
		}

		if ( ! empty( $input['displayName'] ) ) {
			$insert_user_args['display_name'] = $input['displayName'];
		}

		if ( ! empty( $input['nickname'] ) ) {
			$insert_user_args['nickname'] = $input['nickname'];
		}

		if ( ! empty( $input['firstName'] ) ) {
			$insert_user_args['first_name'] = $input['firstName'];
		}

		if ( ! empty( $input['lastName'] ) ) {
			$insert_user_args['last_name'] = $input['lastName'];
		}

		if ( ! empty( $input['description'] ) ) {
			$insert_user_args['description'] = $input['description'];
		}

		if ( ! empty( $input['richEditing'] ) ) {
			$insert_user_args['rich_editing'] = $input['richEditing'];
		}

		if ( ! empty( $input['registered'] ) ) {
			$insert_user_args['user_registered'] = $input['registered'];
		}

		if ( ! empty( $input['role'] ) ) {
			$insert_user_args['role'] = $input['role'];
		}

		if ( ! empty( $input['jabber'] ) ) {
			$insert_user_args['jabber'] = $input['jabber'];
		}

		if ( ! empty( $input['aim'] ) ) {
			$insert_user_args['aim'] = $input['aim'];
		}

		if ( ! empty( $input['yim'] ) ) {
			$insert_user_args['yim'] = $input['yim'];
		}

		if ( ! empty( $input['locale'] ) ) {
			$insert_user_args['locale'] = $input['locale'];
		}

		/**
		 * Filters the mappings for input to arguments
		 *
		 * @var array  $insert_user_args The arguments to ultimately be passed to the WordPress function
		 * @var array  $input            Input data from the GraphQL mutation
		 * @var string $mutation_name    What user mutation is being performed for context
		 */
		$insert_user_args = apply_filters( 'graphql_user_insert_post_args', $insert_user_args, $input, $mutation_name );

		return $insert_user_args;

	}

}
