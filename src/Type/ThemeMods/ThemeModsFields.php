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
	private static function _default_field($mod_name, $data = null ) {
		return [ 
			'type' 				=> Types::string(),
			'description'	=> $mod_name,
			'resolve'			=> function( $root, $args, $context, $info ) use( $data ) {
				return ( ! empty( $data ) ) ? (string) $data : null;
			}
		];
	}

	/**
	 * background field definition
	 *
	 * @param array $data - theme modification data
	 * @return array - field definition
	 */
	public static function background( $data = [] ) {
		return [ 
			'type' 				=> Types::post_object( 'attachment' ),
			'description'	=> __( 'custom background', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ) use( $data ) {
				return ( ! empty( $data['id'] ) ) ? DataSource::resolve_post_object( absint( $data['id'] ), 'attachment' ) : null;
			}
		];
	}

	/**
	 * backgroundColor field definition
	 *
	 * @param string $data - theme modification data
	 * @return array - field definition
	 */
	public static function background_color( $data = null ) {
		return [ 
			'type' 				=> Types::string(),
			'description'	=> __( 'custom theme logo', 'wp-graphql-extra-options' ),
			'resolve'			=> function() use( $data ) {
				return ( ! empty( $data ) ) ? $data : null;
			}
		];
	}	

	/**
	 * customCssPostId field definition
	 *
	 * @param integer $data - theme modification data
	 * @return array - field definition
	 */
	public static function custom_css_post_id( $data = null ) {
		return [ 
			'type' 				=> Types::int(),
			'description'	=> __( 'custom theme logo', 'wp-graphql-extra-options' ),
			'resolve'			=> function() use( $data ) {
				return ( ! empty( $data ) ) ? $data : null;
			}
		];
	}

	/**
	 * customLogo field definition
	 *
	 * @param integer $data - theme modification data
	 * @return array - field definition
	 */
	public static function custom_logo( $data = null ) {
		return [ 
			'type' 				=> Types::post_object( 'attachment' ),
			'description'	=> __( 'custom theme logo', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ) use( $data ) {
				return ( ! empty( $data ) ) ? DataSource::resolve_post_object( absint( $data ), 'attachment' ) : null;
			}
		];
	}

	/**
	 * headerImage field definition
	 *
	 * @param array $data - theme modification data
	 * @return array - field definition
	 */
	public static function header_image( $data = [] ) {
		return [ 
			'type' 				=> Types::post_object( 'attachment' ),
			'description'	=> __( 'custom header image', 'wp-graphql-extra-options' ),
			'resolve'			=> function( $root, $args, $context, $info ) use( $data ) {
				return ( ! empty( $data['id'] ) ) ? DataSource::resolve_post_object( absint( $data['id'] ), 'attachment' ) : null;
			}
		];
	}

	/**
	 * navMenuLocations field definition
	 *
	 * @param array $data - theme modification data
	 * @return array - field definition
	 */
	public static function nav_menu_locations( $data = null ) {
		return [
			'type' 				=> Types::menu(),
			'description'	=> __( 'theme menu locations', 'wp-graphql-extra-options' ),
			'args'				=> [
				'location' => [
					'type'	=> Types::string(),
					'description' => __( 'theme menu location name', 'wp-graphql-extra-options' )
				],
			],
			'resolve'			=> function($root, $args, $context, $info ) use ( $data ) {
				if ( ! empty( $args[ 'location' ] ) && ! empty ( $data ) ) {
					$location = $args[ 'location' ];
					return ( ! empty( $data[ $location ] ) ) ? DataSource::resolve_term_object( absint( $data[ $location ] ), 'nav_menu' ) : null;
				}
				return null;
			}
		];
	}

}