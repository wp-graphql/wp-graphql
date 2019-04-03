<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

/**
 * Class UserRole - Models data for user roles
 *
 * @property string $id
 * @property string name
 * @property array  $capabilities
 *
 * @package WPGraphQL\Model
 */
class UserRole extends Model {

	/**
	 * Stores the incoming user role to be modeled
	 *
	 * @var array $user_role
	 * @access protected
	 */
	protected $user_role;

	/**
	 * UserRole constructor.
	 *
	 * @param array $user_role The incoming user role to be modeled
	 *
	 * @access public
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( $user_role ) {
		$this->user_role = $user_role;

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );
		}

		parent::__construct( $user_role );
		$this->init();
	}

	/**
	 * Callback for the graphql_data_is_private filter to determine if the role should be
	 * considered private
	 *
	 * @param bool   $private    True or False value if the data should be private
	 * @param string $model_name Name of the model for the data currently being modeled
	 * @param mixed  $data       The Data currently being modeled
	 *
	 * @access public
	 * @return bool
	 */
	public function is_private( $private, $model_name, $data ) {

		if ( $this->get_model_name() !== $model_name ) {
			return $private;
		}

		if ( current_user_can( 'list_users' ) ) {
			return false;
		}

		$current_user_roles = wp_get_current_user()->roles;
		if ( in_array( $data['name'], $current_user_roles, true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Initializes the object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id' => function() {
					$id = Relay::toGlobalId( 'role', $this->user_role['id'] );
					return $id;
				},
				'name' => function() {
					return ! empty( $this->user_role['name'] ) ? esc_html( $this->user_role['name'] ) : null;
				},
				'capabilities' => function() {
					if ( empty( $this->user_role['capabilities'] ) || ! is_array( $this->user_role['capabilities'] ) ) {
						return null;
					} else {
						return array_keys( $this->user_role['capabilities'] );
					}
				}
			];

			parent::prepare_fields();

		}
	}

}
