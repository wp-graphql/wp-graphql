<?php

namespace WPGraphQL\Data;

use Exception;
use GraphQL\Error\UserError;
use GraphQL\Type\Definition\ResolveInfo;
use WPGraphQL\AppContext;

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
	 */
	private static $input_fields = [];

	/**
	 * Defines the accepted input arguments
	 *
	 * @return array|null
	 */
	public static function input_fields() {
		if ( empty( self::$input_fields ) ) {
			$input_fields = [
				'password'    => [
					'type'        => 'String',
					'description' => __( 'A string that contains the plain text password for the user.', 'wp-graphql' ),
				],
				'nicename'    => [
					'type'        => 'String',
					'description' => __( 'A string that contains a URL-friendly name for the user. The default is the user\'s username.', 'wp-graphql' ),
				],
				'websiteUrl'  => [
					'type'        => 'String',
					'description' => __( 'A string containing the user\'s URL for the user\'s web site.', 'wp-graphql' ),
				],
				'email'       => [
					'type'        => 'String',
					'description' => __( 'A string containing the user\'s email address.', 'wp-graphql' ),
				],
				'displayName' => [
					'type'        => 'String',
					'description' => __( 'A string that will be shown on the site. Defaults to user\'s username. It is likely that you will want to change this, for both appearance and security through obscurity (that is if you dont use and delete the default admin user).', 'wp-graphql' ),
				],
				'nickname'    => [
					'type'        => 'String',
					'description' => __( 'The user\'s nickname, defaults to the user\'s username.', 'wp-graphql' ),
				],
				'firstName'   => [
					'type'        => 'String',
					'description' => __( '	The user\'s first name.', 'wp-graphql' ),
				],
				'lastName'    => [
					'type'        => 'String',
					'description' => __( 'The user\'s last name.', 'wp-graphql' ),
				],
				'description' => [
					'type'        => 'String',
					'description' => __( 'A string containing content about the user.', 'wp-graphql' ),
				],
				'richEditing' => [
					'type'        => 'String',
					'description' => __( 'A string for whether to enable the rich editor or not. False if not empty.', 'wp-graphql' ),
				],
				'registered'  => [
					'type'        => 'String',
					'description' => __( 'The date the user registered. Format is Y-m-d H:i:s.', 'wp-graphql' ),
				],
				'roles'       => [
					'type'        => [ 'list_of' => 'String' ],
					'description' => __( 'An array of roles to be assigned to the user.', 'wp-graphql' ),
				],
				'jabber'      => [
					'type'        => 'String',
					'description' => __( 'User\'s Jabber account.', 'wp-graphql' ),
				],
				'aim'         => [
					'type'        => 'String',
					'description' => __( 'User\'s AOL IM account.', 'wp-graphql' ),
				],
				'yim'         => [
					'type'        => 'String',
					'description' => __( 'User\'s Yahoo IM account.', 'wp-graphql' ),
				],
				'locale'      => [
					'type'        => 'String',
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
	 * @param array  $input         Data coming from the GraphQL mutation query input
	 * @param string $mutation_name Name of the mutation being performed
	 *
	 * @return array
	 */
	public static function prepare_user_object( $input, $mutation_name ) {
		$insert_user_args = [];

		/**
		 * Optional fields
		 */
		if ( isset( $input['nicename'] ) ) {
			$insert_user_args['user_nicename'] = $input['nicename'];
		}

		if ( isset( $input['websiteUrl'] ) ) {
			$insert_user_args['user_url'] = esc_url( $input['websiteUrl'] );
		}

		if ( isset( $input['displayName'] ) ) {
			$insert_user_args['display_name'] = $input['displayName'];
		}

		if ( isset( $input['nickname'] ) ) {
			$insert_user_args['nickname'] = $input['nickname'];
		}

		if ( isset( $input['firstName'] ) ) {
			$insert_user_args['first_name'] = $input['firstName'];
		}

		if ( isset( $input['lastName'] ) ) {
			$insert_user_args['last_name'] = $input['lastName'];
		}

		if ( isset( $input['description'] ) ) {
			$insert_user_args['description'] = $input['description'];
		}

		if ( isset( $input['richEditing'] ) ) {
			$insert_user_args['rich_editing'] = $input['richEditing'];
		}

		if ( isset( $input['registered'] ) ) {
			$insert_user_args['user_registered'] = $input['registered'];
		}

		if ( isset( $input['locale'] ) ) {
			$insert_user_args['locale'] = $input['locale'];
		}

		/**
		 * Required fields
		 */
		if ( ! empty( $input['email'] ) ) {
			if ( false === is_email( apply_filters( 'pre_user_email', $input['email'] ) ) ) {
				throw new UserError( esc_html__( 'The email address you are trying to use is invalid', 'wp-graphql' ) );
			}
			$insert_user_args['user_email'] = $input['email'];
		}

		if ( ! empty( $input['password'] ) ) {
			$insert_user_args['user_pass'] = $input['password'];
		} else {
			$insert_user_args['user_pass'] = null;
		}

		if ( ! empty( $input['username'] ) ) {
			$insert_user_args['user_login'] = $input['username'];
		}

		if ( ! empty( $input['roles'] ) ) {
			/**
			 * Pluck the first role out of the array since the insert and update functions only
			 * allow one role to be set at a time. We will add all of the roles passed to the
			 * mutation later on after the initial object has been created or updated.
			 */
			$insert_user_args['role'] = $input['roles'][0];
		}

		/**
		 * Filters the mappings for input to arguments
		 *
		 * @param array  $insert_user_args The arguments to ultimately be passed to the WordPress function
		 * @param array  $input            Input data from the GraphQL mutation
		 * @param string $mutation_name    What user mutation is being performed for context
		 */
		$insert_user_args = apply_filters( 'graphql_user_insert_post_args', $insert_user_args, $input, $mutation_name );

		return $insert_user_args;
	}

	/**
	 * This updates additional data related to the user object after the initial mutation has
	 * happened
	 *
	 * @param int         $user_id       The ID of the user being mutated
	 * @param array       $input         The input data from the GraphQL query
	 * @param string      $mutation_name Name of the mutation currently being run
	 * @param \WPGraphQL\AppContext $context The AppContext passed down the resolve tree
	 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the Resolve Tree
	 *
	 * @return void
	 * @throws \Exception
	 */
	public static function update_additional_user_object_data( $user_id, $input, $mutation_name, AppContext $context, ResolveInfo $info ) {
		$roles = ! empty( $input['roles'] ) ? $input['roles'] : [];
		self::add_user_roles( $user_id, $roles );

		/**
		 * Run an action after the additional data has been updated. This is a great spot to hook into to
		 * update additional data related to users, such as setting relationships, updating additional usermeta,
		 * or sending emails to Kevin... whatever you need to do with the userObject.
		 *
		 * @param int         $user_id       The ID of the user being mutated
		 * @param array       $input         The input for the mutation
		 * @param string      $mutation_name The name of the mutation (ex: create, update, delete)
		 * @param \WPGraphQL\AppContext $context The AppContext passed down the resolve tree
		 * @param \GraphQL\Type\Definition\ResolveInfo $info The ResolveInfo passed down the Resolve Tree
		 */
		do_action( 'graphql_user_object_mutation_update_additional_data', $user_id, $input, $mutation_name, $context, $info );
	}

	/**
	 * Method to add user roles to a user object
	 *
	 * @param int   $user_id The ID of the user
	 * @param array $roles   List of roles that need to get added to the user
	 *
	 * @return void
	 * @throws \Exception
	 */
	private static function add_user_roles( $user_id, $roles ) {
		if ( empty( $roles ) || ! is_array( $roles ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		$user = get_user_by( 'ID', $user_id );

		if ( false !== $user ) {
			foreach ( $roles as $role ) {
				$verified = self::verify_user_role( $role, $user_id );

				if ( true === $verified ) {
					$user->add_role( $role );
				} elseif ( is_wp_error( $verified ) ) {
					throw new Exception( esc_html( $verified->get_error_message() ) );
				} elseif ( false === $verified ) {
					// Translators: The placeholder is the name of the user role
					throw new Exception( esc_html( sprintf( __( 'The %s role cannot be added to this user', 'wp-graphql' ), $role ) ) );
				}
			}
		}
	}

	/**
	 * Method to check if the user role is valid, and if the current user has permission to add, or
	 * remove it from a user.
	 *
	 * @param string $role    Name of the role trying to get added to a user object
	 * @param int    $user_id The ID of the user being mutated
	 *
	 * @return mixed|bool|\WP_Error
	 */
	private static function verify_user_role( $role, $user_id ) {
		global $wp_roles;

		$potential_role = isset( $wp_roles->role_objects[ $role ] ) ? $wp_roles->role_objects[ $role ] : '';

		if ( empty( $wp_roles->role_objects[ $role ] ) ) {
			// Translators: The placeholder is the name of the user role
			return new \WP_Error( 'wpgraphql_user_invalid_role', sprintf( __( 'The role %s does not exist', 'wp-graphql' ), $role ) );
		}

		/*
		 * Don't let anyone with 'edit_users' (admins) edit their own role to something without it.
		 * Multisite super admins can freely edit their blog roles -- they possess all caps.
		 */
		if (
			! ( is_multisite() && current_user_can( 'manage_sites' ) ) &&
			get_current_user_id() === $user_id &&
			! $potential_role->has_cap( 'edit_users' )
		) {
			return new \WP_Error( 'wpgraphql_user_invalid_role', __( 'Sorry, you cannot remove user editing permissions for your own account.', 'wp-graphql' ) );
		}

		/**
		 * The function for this is only loaded on admin pages. See note: https://codex.wordpress.org/Function_Reference/get_editable_roles#Notes
		 */
		if ( ! function_exists( 'get_editable_roles' ) ) {
			require_once ABSPATH . 'wp-admin/includes/admin.php';
		}

		$editable_roles = get_editable_roles();

		if ( empty( $editable_roles[ $role ] ) ) {
			// Translators: %s is the name of the role that can't be added to the user.
			return new \WP_Error( 'wpgraphql_user_invalid_role', sprintf( __( 'Sorry, you are not allowed to give this the following role: %s.', 'wp-graphql' ), $role ) );
		} else {
			return true;
		}
	}
}
