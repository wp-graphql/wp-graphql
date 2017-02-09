<?php
namespace WPGraphQL;

use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use GraphQL\Type\Definition\Type;
use WPGraphQL\Type\RootQueryType;
use WPGraphQL\Type\CommentType;
use WPGraphQL\Type\OptionType;
use WPGraphQL\Type\PluginType;
use WPGraphQL\Type\PostObjectType;
use WPGraphQL\Type\PostTypeType;
use WPGraphQL\Type\ShortcodeType;
use WPGraphQL\Type\TaxonomyType;
use WPGraphQL\Type\TermObjectType;
use WPGraphQL\Type\ThemeType;
use WPGraphQL\Type\UserType;

/**
 * Class Types
 *
 * Acts as a registry and factory for Types.
 *
 * @package WPGraphQL
 */
class Types {

	private static $root_query;
	private static $comment;
	private static $option;
	private static $plugin;
	private static $post_object;
	private static $post_object_type;
	private static $shortcode;
	private static $taxonomy;
	private static $term_object;
	private static $theme;
	private static $user;

	public static function root_query() {
		return self::$root_query ?: ( self::$root_query = new RootQueryType() );
	}

	public static function comment() {
		return self::$comment ?: ( self::$comment = new CommentType() );
	}

	public static function option() {
		return self::$option ?: ( self::$option = new OptionType() );
	}

	public static function plugin() {
		return self::$plugin ?: ( self::$plugin = new PluginType() );
	}

	public static function post_object( $post_type ) {
		return self::$post_object->{ $post_type } ?: ( self::$post_object->{ $post_type } = new PostObjectType( $post_type ) );
	}

	public static function post_object_type() {
		return self::$post_object_type ?: ( self::$post_object_type = new PostTypeType() );
	}

	public static function shortcode() {
		return self::$shortcode ?: ( self::$shortcode = new ShortcodeType() );
	}

	public static function taxonomy() {
		return self::$taxonomy ?: ( self::$taxonomy = new TaxonomyType() );
	}

	public static function term_object( $taxonomy ) {
		return self::$term_object->{ $taxonomy } ?: ( self::$term_object->{ $taxonomy } = new TermObjectType( $taxonomy ) );
	}

	public static function theme() {
		return self::$theme ?: ( self::$theme = new ThemeType() );
	}

	public static function user() {
		return self::$user ?: ( self::$user = new UserType() );
	}

	public static function boolean() {
		return Type::boolean();
	}
	/**
	 * @return \GraphQL\Type\Definition\FloatType
	 */
	public static function float() {
		return Type::float();
	}
	/**
	 * @return \GraphQL\Type\Definition\IDType
	 */
	public static function id() {
		return Type::id();
	}
	/**
	 * @return \GraphQL\Type\Definition\IntType
	 */
	public static function int() {
		return Type::int();
	}
	/**
	 * @return \GraphQL\Type\Definition\StringType
	 */
	public static function string() {
		return Type::string();
	}
	/**
	 * @param Type $type
	 * @return ListOfType
	 */
	public static function list_of( $type ) {
		return new ListOfType( $type );
	}
	/**
	 * @param Type $type
	 * @return NonNull
	 */
	public static function non_null( $type ) {
		return new NonNull( $type );
	}

}
