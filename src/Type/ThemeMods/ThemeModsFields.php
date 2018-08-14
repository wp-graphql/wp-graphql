<?php

namespace WPGraphQL\Type\ThemeMods;

use WPGraphQL\Data\DataSource;
use WPGraphQL\Type\PostObject\Connection\PostObjectConnectionDefinition;
use WPGraphQL\Type\WPObjectType;
use WPGraphQL\Types;

/**
 * Class ThemeModsField
 *
 * Dynamically provides ThemeModsType field definitions
 *
 * @since 0.0.32
 * @package WPGraphQL\Type\ThemeMods
 */
class ThemeModsFields {

	/**
	 * Field Resolver
	 *
	 * @param string mod_name - name of theme modification being resolved
	 * @param array $args - data being passed to resolver
	 * @return array|null
	 */
  public static function __callStatic( $mod_name, $args ) {

		if( method_exists( __CLASS__, $mod_name) ) {
			return self::$mod_name( ...$args );
		}

		return self::_default_field( $mod_name, ...$args );
	}

	/**
	 * Default field definition
	 *
	 * @param string $mod_name - name of theme modification being defined
	 * @param mixed $data
	 * @return array
	 */
	private static function _default_field($mod_name) {
		return [ 
			'type' 				=> Types::string(),
			'description'	=> $mod_name,
			'resolve'			=> function( $root, $args, $context, $info ) use( $mod_name ) {
				return ( ! empty( $root[ $mod_name ] ) ) ? (string) $root[ $mod_name ] : null;
			}
		];
	}

	/**
	 * background field definition
	 * TODO - create a more complex object type to hold context data as well as attachment post object
	 *
	 * @param array $data - theme modification data
	 * @return array - field definition
	 */
	public static function background() {
		return [ 
			'type' 				=> Types::post_object( 'attachment' ),
			'description'	=> __( 'custom background', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ) {
				if( ! empty( $root['background'] ) ) { 
					return ( ! empty( $root['background']['id'] ) ) ?
						DataSource::resolve_post_object( absint( $root['background']['id'] ), 'attachment' ) :
						null;
				}

				return null;
			}
		];
	}

	/**
	 * backgroundColor field definition
	 *
	 * @param string $data - theme modification data
	 * @return array - field definition
	 */
	public static function background_color() {
		return [ 
			'type' 				=> Types::string(),
			'description'	=> __( 'background color', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ) {
				return ( ! empty( $root['background_color'] ) ) ? $root['background_color'] : null;
			}
		];
	}	

	/**
	 * customCssPostId field definition
	 *
	 * @param integer $data - theme modification data
	 * @return array - field definition
	 */
	public static function custom_css_post_id(){
		return [ 
			'type' 				=> Types::int(),
			'description'	=> __( 'custom theme logo', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ) {
				return ( ! empty( $root['custom_css_post_id'] ) ) ? $root['custom_css_post_id'] : null;
			}
		];
	}

	/**
	 * customLogo field definition
	 *
	 * @param integer $data - theme modification data
	 * @return array - field definition
	 */
	public static function custom_logo() {
		return [ 
			'type' 				=> Types::post_object( 'attachment' ),
			'description'	=> __( 'custom theme logo', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ){
				return ( ! empty( $root['custom_logo'] ) ) ? DataSource::resolve_post_object( absint( $root['custom_logo'] ), 'attachment' ) : null;
			}
		];
	}

	/**
	 * headerImage field definition
	 * TODO - create a more complex object type to hold context data as well as attachment post object
	 *
	 * @param array $data - theme modification data
	 * @return array - field definition
	 */
	public static function header_image() {
		return [ 
			'type' 				=> Types::post_object( 'attachment' ),
			'description'	=> __( 'custom header image', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ){
				if( ! empty ( $root['header_image'] ) ) {
					return ( ! empty( $root['header_image']['id'] ) ) ?
						DataSource::resolve_post_object( absint( $root['header_image']['id'] ), 'attachment' ) :
						null;
				}
				
				return null;
			},
		];
	}

	/**
	 * navMenuLocations field definition
	 *
	 * @param array $data - theme modification data
	 * @return array - field definition
	 */
	public static function nav_menu_locations() {
		return [
			'type' 				=> Types::menu(),
			'description'	=> __( 'theme menu locations', 'wp-graphql-extra-options' ),
			'args'				=> [
				'location' => [
					'type'	=> Types::string(),
					'description' => __( 'theme menu location name', 'wp-graphql-extra-options' )
				],
			],
			'resolve'			=> function($root, $args, $context, $info ) {
				if ( ! empty( $args[ 'location' ] ) && ! empty ( $root['nav_menu_locations'] ) ) {
					$location = $args[ 'location' ];
					return ( ! empty( $root['nav_menu_locations'][ $location ] ) ) ?
						DataSource::resolve_term_object( absint( $root['nav_menu_locations'][ $location ] ), 'nav_menu' ) :
						null;
				}

				return null;
			}
		];
	}

}