<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class Menu extends Model {

	protected $menu;

	protected $fields;

	public function __construct( \WP_Term $term, $filter = null ) {

		if ( empty( $term ) ) {
			throw new \Exception( __( 'An empty WP_Term object was used to initialize this object', 'wp-graphql' ) );
		}

		$this->menu = $term;

		parent::__construct( 'menuObject', $term );
		$this->init( $filter );

	}

	public function init( $fields = null ) {

		if ( null === $this->fields ) {
			$this->fields = [
				'id' => function() {
					return ! empty( $this->menu->term_id ) ? Relay::toGlobalId( 'Menu', $this->menu->term_id ) : null;
				},
				'count' => function() {
					return ! empty( $this->menu->count ) ? absint( $this->menu->count ) : null;
				},
				'menuId' => function() {
					return ! empty( $this->menu->term_id ) ? $this->menu->term_id : null;
				},
				'name' => function() {
					return ! empty( $this->menu->name ) ? $this->menu->name : null;
				},
				'slug' => function() {
					return ! empty( $this->menu->slug ) ? $this->menu->slug : null;
				}
			];
		}

		$this->prepare_fields( $this->fields, $fields );

	}
}
