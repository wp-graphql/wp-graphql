<?php

namespace WPGraphQL\Model;

use GraphQLRelay\Relay;

/**
 * Class Theme - Models data for themes
 *
 * @property ?string       $author
 * @property ?string       $authorUri
 * @property ?string       $description
 * @property ?string       $id
 * @property ?string       $name
 * @property ?string       $screenshot
 * @property ?string       $slug
 * @property ?string       $themeUri
 * @property string[]|null $tags
 * @property ?string       $version
 *
 * @package WPGraphQL\Model
 *
 * @extends \WPGraphQL\Model\Model<\WP_Theme>
 */
class Theme extends Model {
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
	 * {@inheritDoc}
	 */
	protected function is_private() {
		// Don't assume a capabilities hierarchy, since it's likely headless sites might disable some capabilities site-wide.
		if ( current_user_can( 'edit_themes' ) || current_user_can( 'switch_themes' ) || current_user_can( 'update_themes' ) ) {
			return false;
		}

		if ( wp_get_theme()->get_stylesheet() !== $this->data->get_stylesheet() ) {
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
				'author'      => function () {
					return ! empty( $this->data->author ) ? $this->data->author : null;
				},
				'authorUri'   => function () {
					$author_uri = $this->data->get( 'AuthorURI' );
					return ! empty( $author_uri ) ? $author_uri : null;
				},
				'description' => function () {
					return ! empty( $this->data->description ) ? $this->data->description : null;
				},
				'id'          => function () {
					return ! empty( $this->slug ) ? Relay::toGlobalId( 'theme', $this->slug ) : null;
				},
				'name'        => function () {
					$name = $this->data->get( 'Name' );
					return ! empty( $name ) ? $name : null;
				},
				'screenshot'  => function () {
					$screenshot = $this->data->get_screenshot();
					return ! empty( $screenshot ) ? $screenshot : null;
				},
				'slug'        => function () {
					$stylesheet = $this->data->get_stylesheet();
					return ! empty( $stylesheet ) ? $stylesheet : null;
				},
				'themeUri'    => function () {
					$theme_uri = $this->data->get( 'ThemeURI' );
					return ! empty( $theme_uri ) ? $theme_uri : null;
				},
				'tags'        => function () {
					return ! empty( $this->data->tags ) ? $this->data->tags : null;
				},
				'version'     => function () {
					return ! empty( $this->data->version ) ? (string) $this->data->version : null;
				},
			];
		}
	}
}
