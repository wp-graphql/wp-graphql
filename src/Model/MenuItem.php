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
 * @property int    $databaseId
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
	 * Determines whether a MenuItem should be considered private.
	 *
	 * If a MenuItem is not connected to a menu that's assigned to a location
	 * it's not considered a public node
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function is_private() {

		// If the current user can edit theme options, consider the menu item public
		if ( current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		// Get menu locations for the active theme
		$locations = get_theme_mod( 'nav_menu_locations' );

		// If there are no menu locations, consider the MenuItem private
		if ( empty( $locations ) ) {
			return true;
		}

		// Get the values of the locations
		$location_ids = array_values( $locations );
		$menus        = wp_get_object_terms( $this->data->ID, 'nav_menu', [ 'fields' => 'ids' ] );

		// If there are no menus
		if ( empty( $menus ) ) {
			return true;
		}

		if ( is_wp_error( $menus ) ) {
			throw new \Exception( sprintf( __( 'No menus could be found for menu item %s', 'wp-graphql' ), $this->data->ID ) );
		}
		$menu_id = $menus[0];
		if ( empty( $location_ids ) || ! in_array( $menu_id, $location_ids, true ) ) {
			return true;
		}

		return false;
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
					return ( ! empty( $this->data->title ) ) ? $this->html_entity_decode( $this->data->title, 'label', true ) : null;
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
				'path'             => function() {

					$url = $this->url;

					if ( ! empty( $url ) ) {
						$parsed = wp_parse_url( $url );
						if ( isset( $parsed['host'] ) ) {
							if ( strpos( home_url(), $parsed['host'] ) ) {
								return $parsed['path'];
							} elseif ( strpos( home_url(), $parsed['host'] ) ) {
								return $parsed['path'];
							}
						}
					}
					return $url;

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
