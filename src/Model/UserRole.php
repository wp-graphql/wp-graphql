<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class UserRole extends Model {

	protected $user_role;

	public function __construct( $user_role ) {
		$this->user_role = $user_role;
		parent::__construct( 'UserRoleObject', $user_role, 'list_users', [ 'id', 'name' ] );
		$this->init();
	}

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
					return esc_html( $this->user_role['name'] );
				},
				'capabilities' => function() {
					if ( empty( $this->user_role['capabilities'] ) ) {
						return null;
					} else {
						return array_keys( $this->user_role['capabilities'] );
					}
				}
			];
		}
	}

}
