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
	 * @var \WP_Theme $theme
	 * @access protected
	 */
	protected $theme;

	/**
	 * Theme constructor.
	 *
	 * @param \WP_Theme $theme The incoming WP_Theme to be modeled
	 *
	 * @return void
	 * @access public
	 * @throws \Exception
	 */
	public function __construct( \WP_Theme $theme ) {

		$this->theme = $theme;

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );
		}

		parent::__construct( $this->theme );
		$this->init();

	}

	/**
	 * Callback for the graphql_data_is_private filter to determine if the post should be
	 * considered private. The theme should be considered private unless it is the current active
	 * theme. The current active theme is public because all of the information can be retrieved by
	 * viewing source on the site and looking for the style.css file.
	 *
	 * @param bool   $private    True or False value if the data should be private
	 * @param string $model_name Name of the model for the data currently being modeled
	 * @param mixed  $data       The Data currently being modeled
	 *
	 * @access public
	 * @return bool
	 */
	public function is_private( $private, $model_name, $data ) {

		if ( $this->get_model_name() !== $model_name ) {
			return $private;
		}

		if ( current_user_can( 'edit_themes' ) ) {
			return false;
		}

		if ( wp_get_theme()->get_stylesheet() !== $data->get_stylesheet() ) {
			return true;
		}

		return $private;

	}

	/**
	 * Initialize the object
	 *
	 * @access protected
	 * @return void
	 */
	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			return;
		}

		if ( empty( $this->fields ) ) {
			$this->fields = [
				'id' => function() {
					$stylesheet = $this->theme->get_stylesheet();
					return ( ! empty( $stylesheet ) ) ? Relay::toGlobalId( 'theme', $stylesheet ) : null;
				},
				'slug' => function() {
					$stylesheet = $this->theme->get_stylesheet();
					return ! empty( $stylesheet ) ? $stylesheet : null;
				},
				'name' => function() {
					$name = $this->theme->get( 'Name' );
					return ! empty( $name ) ? $name : null;
				},
				'screenshot' => function() {
					$screenshot = $this->theme->get_screenshot();
					return ! empty( $screenshot ) ? $screenshot : null;
				},
				'themeUri' => function() {
					$theme_uri = $this->theme->get( 'ThemeURI' );
					return ! empty( $theme_uri ) ? $theme_uri : null;
				},
				'description' => function() {
					return ! empty( $this->theme->description ) ? $this->theme->description : null;
				},
				'author' => function() {
					return ! empty( $this->theme->author ) ? $this->theme->author : null;
				},
				'authorUri' => function() {
					$author_uri = $this->theme->get( 'AuthorURI' );
					return ! empty( $author_uri ) ? $author_uri : null;
				},
				'tags' => function() {
					return ! empty( $this->theme->tags ) ? $this->theme->tags : null;
				},
				'version' => function() {
					return ! empty( $this->theme->version ) ? $this->theme->version : null;
				}
			];

			parent::prepare_fields();

		}
	}
}