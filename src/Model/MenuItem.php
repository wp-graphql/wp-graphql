<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class MenuItem extends Model {

	public $post;

	public $fields;

	public function __construct( \WP_Post $post, $filter = null ) {

		if ( empty( $post ) ) {
			throw new \Exception( __( 'An empty WP_Post object was used to initialize this object', 'wp-graphql' ) );
		}

		$this->post = $post;

		parent::__construct( 'menuItem', $post );

		$this->init( $filter );

	}

	public function init( $filter = null ) {

		if ( empty( $fields ) ) {
			$this->fields = [
				'id' => function() {
					return ! empty( $this->post->ID ) ? Relay::toGlobalId( 'nav_menu_item', $this->post->ID ) : null;
				},
				'cassClasses' => function() {
					// If all we have is a non-array or an array with one empty
					// string, return an empty array.
					if ( ! isset( $this->post->classes ) || ! is_array( $this->post->classes ) || empty( $this->post->classes ) || empty( $this->menu_item->classes[0] ) ) {
						return [];
					}

					return $this->menu_item->classes;
				},
				'description' => function() {
					return ( ! empty( $this->post->description ) ) ? $this->post->description : null;
				},
				'label' => function() {
					return ( ! empty( $this->post->title ) ) ? $this->post->title : null;
				},
				'linkRelationship' => function() {
					return ! empty( $this->post->xfn ) ? $this->post->xfn : null;
				},
				'menuItemId' => function() {
					return absint( $this->post->ID );
				},
				'target' => function() {
					return ! empty( $this->post->target ) ? $this->post->target : null;
				},
				'title' => function() {
					return ( ! empty( $this->post->attr_title ) ) ? $this->post->attr_title : null;
				},
				'url' => function() {
					return ! empty( $this->post->url ) ? $this->post->url : null;
				},
			];

			if ( ! empty( $this->post->menu ) ) {
				$this->fields['menu'] = function() {
					$this->post->menu;
				};
			}
		}

		parent::prepare_fields( $this->fields, $filter );
	}

}
