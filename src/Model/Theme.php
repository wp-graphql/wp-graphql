<?php

namespace WPGraphQL\Model;


use GraphQLRelay\Relay;

class Theme extends Model {

	protected $theme;

	public function __construct( \WP_Theme $theme ) {

		$this->theme = $theme;

		if ( ! has_filter( 'graphql_data_is_private', [ $this, 'is_private' ] ) ) {
			add_filter( 'graphql_data_is_private', [ $this, 'is_private' ], 1, 3 );
		}

		parent::__construct( 'ThemeObject', $this->theme );
		$this->init();

	}

	public function is_private( $private, $model_name, $data ) {

		if ( 'ThemeObject' !== $model_name ) {
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

	protected function init() {

		if ( 'private' === $this->get_visibility() ) {
			$this->theme = null;
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