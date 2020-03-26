<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class Theme - Models data for themes
 *
 * @property string     $id
 * @property string     $slug
 * @property string     $name
 * @property string     $screenshot
 * @property string     $themeUri
 * @property string     $description
 * @property string     $author
 * @property string     $authorUri
 * @property array      $tags
 * @property string|int $version
 *
 * @package WPGraphQL\Model
 */
class Theme extends Model {

	/**
	 * Stores the incoming WP_Theme to be modeled
	 *
	 * @var \WP_Theme $data
	 */
	protected $data;

	/**
	 * Theme constructor.
	 *
	 * @param \WP_Theme $theme The incoming WP_Theme to be modeled
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function __construct( \WP_Theme $theme ) {
		$this->data = $theme;
		parent::__construct();
	}

	/**
	 * Method for determining if the data should be considered private or not
	 *
	 * @return bool
	 */
	protected function is_private() {

		if ( current_user_can( 'edit_themes' ) ) {
			return false;
		}

		if ( wp_get_theme()->get_stylesheet() !== $this->data->get_stylesheet() ) {
			return true;
		}

		return false;

	}

	/**
	 * Initialize the object
	 *
	 * @return void
	 */
	protected function init() {

		if ( empty( $this->fields ) ) {

			$this->fields = [
				'id'          => function() {
					$stylesheet = $this->data->get_stylesheet();
					return ( ! empty( $stylesheet ) ) ? Relay::toGlobalId( 'theme', $stylesheet ) : null;
				},
				'slug'        => function() {
					$stylesheet = $this->data->get_stylesheet();
					return ! empty( $stylesheet ) ? $stylesheet : null;
				},
				'name'        => function() {
					$name = $this->data->get( 'Name' );
					return ! empty( $name ) ? $name : null;
				},
				'screenshot'  => function() {
					$screenshot = $this->data->get_screenshot();
					return ! empty( $screenshot ) ? $screenshot : null;
				},
				'themeUri'    => function() {
					$theme_uri = $this->data->get( 'ThemeURI' );
					return ! empty( $theme_uri ) ? $theme_uri : null;
				},
				'description' => function() {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'author'      => function() {
					return ! empty( $this->data->author ) ? $this->data->author : null;
				},
				'authorUri'   => function() {
					$author_uri = $this->data->get( 'AuthorURI' );
					return ! empty( $author_uri ) ? $author_uri : null;
				},
				'tags'        => function() {
					return ! empty( $this->data->tags ) ? $this->data->tags : null;
				},
				'version'     => function() {
					return ! empty( $this->data->version ) ? $this->data->version : null;
				},
			];

		}
	}
}
