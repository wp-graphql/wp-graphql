<?php

namespace WPGraphQL\Model;

use Exception;
use GraphQL\Error\UserError;
use GraphQLRelay\Relay;
use WP_Post;

/**
 * Class MenuItem - Models the data for the MenuItem object type
 *
 * @property string[]      $cssClasses
 * @property int           $databaseId
 * @property ?string       $description
 * @property ?string       $id
 * @property ?string       $label
 * @property ?string       $linkRelationship
 * @property string[]|null $locations
 * @property ?int          $menuDatabaseId
 * @property ?string       $menuId
 * @property int           $objectId
 * @property ?int          $parentDatabaseId
 * @property ?string       $parentId
 * @property ?string       $target
 * @property ?string       $title
 * @property ?string       $uri
 * @property ?string       $url
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<object|mixed>
 */
class MenuItem extends Model {
	/**
	 * MenuItem constructor.
	 *
	 * @param \WP_Post $post The incoming WP_Post object that needs modeling
	 *
	 * @return void
	 */
	public function __construct( WP_Post $post ) {
		$this->data = wp_setup_nav_menu_item( $post );
		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 *
	 * If a MenuItem is not connected to a menu that's assigned to a location
	 * it's not considered a public node.
	 *
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
			// translators: %s is the menu item ID.
			throw new Exception( esc_html( sprintf( __( 'No menus could be found for menu item %s', 'wp-graphql' ), $this->data->ID ) ) );
		}

		$menu_id = $menus[0];
		if ( empty( $location_ids ) || ! in_array( $menu_id, $location_ids, true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'cssClasses'       => function () {
					// If all we have is a non-array or an array with one empty
					// string, return an empty array.
					if ( ! isset( $this->data->classes ) || ! is_array( $this->data->classes ) || empty( $this->data->classes ) || empty( $this->data->classes[0] ) ) {
						return [];
					}

					return $this->data->classes;
				},
				'databaseId'       => function () {
					return absint( $this->data->ID );
				},
				'description'      => function () {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'id'               => function () {
					return ! empty( $this->databaseId ) ? Relay::toGlobalId( 'post', (string) $this->databaseId ) : null;
				},
				'label'            => function () {
					return ! empty( $this->data->title ) ? $this->html_entity_decode( $this->data->title, 'label', true ) : null;
				},
				'linkRelationship' => function () {
					return ! empty( $this->data->xfn ) ? $this->data->xfn : null;
				},
				'locations'        => function () {
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
				'menuDatabaseId'   => function () {
					$menus = wp_get_object_terms( $this->data->ID, 'nav_menu' );
					if ( is_wp_error( $menus ) ) {
						throw new UserError( esc_html( $menus->get_error_message() ) );
					}

					return ! empty( $menus[0]->term_id ) ? $menus[0]->term_id : null;
				},
				'menuId'           => function () {
					return ! empty( $this->menuDatabaseId ) ? Relay::toGlobalId( 'term', (string) $this->menuDatabaseId ) : null;
				},
				'objectId'         => function () {
					return absint( $this->data->object_id );
				},
				'order'            => function () {
					return $this->data->menu_order;
				},
				'parentDatabaseId' => function () {
					return $this->data->menu_item_parent;
				},
				'parentId'         => function () {
					return ! empty( $this->parentDatabaseId ) ? Relay::toGlobalId( 'post', (string) $this->parentDatabaseId ) : null;
				},
				'path'             => function () {
					$url = $this->url;

					if ( ! empty( $url ) ) {
						/** @var array<string,mixed> $parsed */
						$parsed = wp_parse_url( $url );
						if ( isset( $parsed['host'] ) && strpos( home_url(), $parsed['host'] ) ) {
							return $parsed['path'];
						}
					}

					return $url;
				},
				'target'           => function () {
					return ! empty( $this->data->target ) ? $this->data->target : null;
				},
				'title'            => function () {
					return ( ! empty( $this->data->attr_title ) ) ? $this->data->attr_title : null;
				},
				'uri'              => function () {
					$url = $this->url;

					return ! empty( $url ) ? str_ireplace( home_url(), '', $url ) : null;
				},
				'url'              => function () {
					return ! empty( $this->data->url ) ? $this->data->url : null;
				},

				// Deprecated.
				'menuItemId'       => function () {
					return $this->databaseId;
				},
			];
		}
	}
}
