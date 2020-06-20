<?php

namespace WPGraphQL\Model;

use GraphQL\Error\UserError;
use GraphQLRelay\Relay;

/**
 * Class MenuItem - Models the data for the MenuItem object type
 *
 * @property string $id
 * @property array  $cssClasses
 * @property string $description
 * @property string $label
 * @property string $linkRelationship
 * @property int    $menuItemId
 * @property int    $objectId
 * @property string $target
 * @property string $title
 * @property string $url
 * @property string $menuId
 * @property int    $menuDatabaseId
 * @property array  $locations
 *
 * @package WPGraphQL\Model
 */
class MenuItem extends Model {

	/**
	 * Stores the incoming post data
	 *
	 * @var \WP_Post $data
	 */
	protected $data;

	/**
	 * MenuItem constructor.
	 *
	 * @param \WP_Post $post The incoming WP_Post object that needs modeling
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Post $post ) {
		$this->data = wp_setup_nav_menu_item( $post );
		parent::__construct();
	}

	/**
	 * Initialize the Post object
	 *
	 * @return void
	 */
	protected function init() {

		if ( empty( $fields ) ) {

			$this->fields = [
				'id'               => function() {
					return ! empty( $this->data->ID ) ? Relay::toGlobalId( 'nav_menu_item', $this->data->ID ) : null;
				},
				'parentId'         => function() {
					return ! empty( $this->data->menu_item_parent ) ? Relay::toGlobalId( 'nav_menu_item', $this->data->menu_item_parent ) : null;
				},
				'parentDatabaseId' => function() {
					return $this->data->menu_item_parent;
				},
				'cssClasses'       => function() {
					// If all we have is a non-array or an array with one empty
					// string, return an empty array.
					if ( ! isset( $this->data->classes ) || ! is_array( $this->data->classes ) || empty( $this->data->classes ) || empty( $this->data->classes[0] ) ) {
						return [];
					}

					return $this->data->classes;
				},
				'description'      => function() {
					return ( ! empty( $this->data->description ) ) ? $this->data->description : null;
				},
				'label'            => function() {
					return ( ! empty( $this->data->title ) ) ? $this->data->title : null;
				},
				'linkRelationship' => function() {
					return ! empty( $this->data->xfn ) ? $this->data->xfn : null;
				},
				'menuItemId'       => function() {
					return absint( $this->data->ID );
				},
				'databaseId'       => function() {
					return absint( $this->data->ID );
				},
				'objectId'         => function() {
					return ( absint( $this->data->object_id ) );
				},
				'target'           => function() {
					return ! empty( $this->data->target ) ? $this->data->target : null;
				},
				'title'            => function() {
					return ( ! empty( $this->data->attr_title ) ) ? $this->data->attr_title : null;
				},
				'url'              => function() {
					return ! empty( $this->data->url ) ? $this->data->url : null;
				},
				'order'            => function() {
					return $this->data->menu_order;
				},
				'menuId'           => function() {
					return ! empty( $this->menuDatabaseId ) ? Relay::toGlobalId( 'term', (string) $this->menuDatabaseId ) : null;
				},
				'menuDatabaseId'   => function() {

					$menus = wp_get_object_terms( $this->data->ID, 'nav_menu' );
					if ( is_wp_error( $menus ) ) {
						throw new UserError( $menus->get_error_message() );
					}

					return isset( $menus[0] ) && isset( $menus[0]->term_id ) ? $menus[0]->term_id : null;
				},
				'locations'        => function() {

					if ( empty( $this->menuDatabaseId ) ) {
						return null;
					}

					$menu_locations = get_theme_mod( 'nav_menu_locations' );

					if ( empty( $menu_locations ) || ! is_array( $menu_locations ) ) {
						return null;
					}

					$locations = null;
					foreach ( $menu_locations as $location => $id ) {
						if ( absint( $id ) === ( $this->menuDatabaseId ) ) {
							$locations[] = $location;
						}
					}

					return $locations;

				},
			];

		}

	}

}
