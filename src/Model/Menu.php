<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

/**
 * Class Menu - Models data for Menus
 *
 * @property string $id
 * @property int    $count
 * @property int    $menuId
 * @property string $name
 * @property string $slug
 *
 * @package WPGraphQL\Model
 */
class Menu extends Model {

	/**
	 * Stores the incoming WP_Term object
	 *
	 * @var \WP_Term $menu
	 * @access protected
	 */
	protected $menu;

	/**
	 * Stores the fields for the object
	 *
	 * @var null|array $fields
	 * @access protected
	 */
	protected $fields;

	/**
	 * Menu constructor.
	 *
	 * @param \WP_Term          $term   The incoming WP_Term object that needs modeling
	 * @param null|string|array $filter The field or fields to build in the modeled object. You can
	 *                                  pass null to build all of the fields, a string to only
	 *                                  build an object with one field, or an array of field keys
	 *                                  to build an object with those keys and their respective
	 *                                  values.
	 *
	 * @access public
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Term $term, $filter = null ) {

		if ( empty( $term ) ) {
			throw new \Exception( __( 'An empty WP_Term object was used to initialize this object', 'wp-graphql' ) );
		}

		$this->menu = $term;

		parent::__construct( 'menuObject', $term );
		$this->init( $filter );

	}

	/**
	 * Initializes the Menu object
	 *
	 * @param null|string|array $fields The field or fields to build in the modeled object. You can
	 *                                  pass null to build all of the fields, a string to only
	 *                                  build an object with one field, or an array of field keys
	 *                                  to build an object with those keys and their respective
	 *                                  values.
	 *
	 * @access public
	 * @return void
	 */
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
