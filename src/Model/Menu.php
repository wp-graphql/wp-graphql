<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class Menu - Models data for Menus
 *
 * @property string $id
 * @property int    $count
 * @property int    $menuId
 * @property int    $databaseId
 * @property string $name
 * @property string $slug
 *
 * @package WPGraphQL\Model
 */
class Menu extends Model {

	/**
	 * Stores the incoming WP_Term object
	 *
	 * @var \WP_Term $data
	 */
	protected $data;

	/**
	 * Menu constructor.
	 *
	 * @param \WP_Term $term The incoming WP_Term object that needs modeling
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Term $term ) {
		$this->data = $term;
		parent::__construct();
	}

	/**
	 * Determines whether a Menu should be considered private.
	 *
	 * If a Menu is not connected to a menu that's assigned to a location
	 * it's not considered a public node
	 *
	 * @return bool
	 * @throws \Exception
	 */
	public function is_private() {

		// If the current user can edit theme options, consider the menu public
		if ( current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		$locations = get_theme_mod( 'nav_menu_locations' );
		if ( empty( $locations ) ) {
			return true;
		}
		$location_ids = array_values( $locations );
		if ( empty( $location_ids ) || ! in_array( $this->data->term_id, array_values( $location_ids ), true ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Initializes the Menu object
	 *
	 * @return void
	 */
	protected function init() {
		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id'         => function () {
					return ! empty( $this->data->term_id ) ? Relay::toGlobalId( 'term', (string) $this->data->term_id ) : null;
				},
				'count'      => function () {
					return ! empty( $this->data->count ) ? absint( $this->data->count ) : null;
				},
				'menuId'     => function () {
					return ! empty( $this->data->term_id ) ? absint( $this->data->term_id ) : null;
				},
				'databaseId' => function () {
					return ! empty( $this->data->term_id ) ? absint( $this->data->term_id ) : null;
				},
				'name'       => function () {
					return ! empty( $this->data->name ) ? $this->data->name : null;
				},
				'slug'       => function () {
					return ! empty( $this->data->slug ) ? urldecode( $this->data->slug ) : null;
				},
				'locations'  => function () {
					$menu_locations = get_theme_mod( 'nav_menu_locations' );

					if ( empty( $menu_locations ) || ! is_array( $menu_locations ) ) {
						return null;
					}

					$locations = null;
					foreach ( $menu_locations as $location => $id ) {
						if ( absint( $id ) === ( $this->data->term_id ) ) {
							$locations[] = $location;
						}
					}

					return $locations;
				},
			];
		}
	}
}
