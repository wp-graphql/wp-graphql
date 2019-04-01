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
		parent::__construct( 'UserRoleObject', $user_role, 'list_users', [ 'id', 'name' ] );
		$this->init();
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
