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
	 * @var \WP_Term $data
	 * @access protected
	 */
	protected $data;

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
		$this->data = $term;
		parent::__construct();
	}

	/**
	 * Initializes the Menu object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id'     => function() {
					return ! empty( $this->data->term_id ) ? Relay::toGlobalId( 'Menu', $this->data->term_id ) : null;
				},
				'count'  => function() {
					return ! empty( $this->data->count ) ? absint( $this->data->count ) : null;
				},
				'menuId' => function() {
					return ! empty( $this->data->term_id ) ? $this->data->term_id : null;
				},
				'name'   => function() {
					return ! empty( $this->data->name ) ? $this->data->name : null;
				},
				'slug'   => function() {
					return ! empty( $this->data->slug ) ? $this->data->slug : null;
				},
			];

		}

	}

}
