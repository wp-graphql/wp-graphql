<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class Menu - Models data for Menus
 *
 * @property ?int          $count
 * @property ?int          $databaseId
 * @property ?string       $id
 * @property string[]|null $locations
 * @property ?string       $name
 * @property ?string       $slug
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_Term>
 */
class Menu extends Model {
	/**
	 * Menu constructor.
	 *
	 * @param \WP_Term $term The incoming WP_Term object that needs modeling
	 *
	 * @return void
	 */
	public function __construct( \WP_Term $term ) {
		$this->data = $term;
		parent::__construct();
	}

	/**
	 * {@inheritDoc}
	 *
	 * If a Menu is not connected to a menu that's assigned to a location
	 * it's not considered a public node.
	 */
	public function is_private() {

		// If the current user can edit theme options, consider the menu public
		if ( current_user_can( 'edit_theme_options' ) ) {
			return false;
		}

		$locations = get_nav_menu_locations();
		if ( empty( $locations ) ) {
			return true;
		}
		$location_ids = array_values( $locations );
		if ( empty( $location_ids ) || ! in_array( $this->data->term_id, $location_ids, true ) ) {
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
				'count'      => function () {
					return ! empty( $this->data->count ) ? absint( $this->data->count ) : null;
				},
				'databaseId' => function () {
					return ! empty( $this->data->term_id ) ? absint( $this->data->term_id ) : null;
				},
				'id'         => function () {
					return ! empty( $this->databaseId ) ? Relay::toGlobalId( 'term', (string) $this->databaseId ) : null;
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
				'name'       => function () {
					return ! empty( $this->data->name ) ? $this->data->name : null;
				},
				'slug'       => function () {
					return ! empty( $this->data->slug ) ? urldecode( $this->data->slug ) : null;
				},

				// Deprecated.
				'menuId'     => function () {
					return $this->databaseId;
				},
			];
		}
	}
}
