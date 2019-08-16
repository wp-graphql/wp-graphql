<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class MenuItem - Models the data for the MenuItem object type
 *
 * @property string   $id
 * @property array    $cssClasses
 * @property string   $description
 * @property string   $label
 * @property string   $linkRelationship
 * @property int      $menuItemId
 * @property int      $objectId
 * @property string   $target
 * @property string   $title
 * @property string   $url
 *
 * @package WPGraphQL\Model
 */
class MenuItem extends Model {

	/**
	 * Stores the incoming post data
	 *
	 * @var \WP_Post $data
	 * @access protected
	 */
	protected $data;

	/**
	 * MenuItem constructor.
	 *
	 * @param \WP_Post $post The incoming WP_Post object that needs modeling
	 *
	 * @access public
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
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( empty( $fields ) ) {

			$this->fields = [
				'id'               => function() {
					return ! empty( $this->data->ID ) ? Relay::toGlobalId( 'nav_menu_item', $this->data->ID ) : null;
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
			];

		}

	}

}
