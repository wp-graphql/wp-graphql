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
	 * Menu constructor.
	 *
	 * @param \WP_Term $term The incoming WP_Term object that needs modeling
	 *
	 * @access public
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Term $term ) {
		$this->menu = $term;
		parent::__construct( $term );
		$this->init();
	}

	/**
	 * Initializes the Menu object
	 *
	 * @access public
	 * @return void
	 */
	public function init() {

		if ( empty( $this->fields ) ) {
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

			parent::prepare_fields();

		}

	}

}
