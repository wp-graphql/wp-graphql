<?php

namespace WPGraphQL\Type\Union;

use WPGraphQL\Types;
use WPGraphQL\Type\WPUnionType;

/**
 * Class CommentAuthorUnionType
 *
 * In some situations, the type of term cannot be known until query time. The commentAuthorUnion allows for a
 * comment author to be queried and resolved to a number of types. Currently will return a user or commentAuthor.
 *
 * @package WPGraphQL\Type\Union
 */
class CommentAuthorUnionType extends WPUnionType {

	/**
	 * This holds an array of the possible types that can be resolved by this union
	 *
	 * @var array
	 */
	private static $possible_types;

	/**
	 * CommentAuthorUnionType constructor.
	 */
	public function __construct() {

		self::getPossibleTypes();

		$config = [
			'name'        => 'CommentAuthorUnion',
			'types'       => self::$possible_types,
			'resolveType' => function( $source ) {
				if ( $source instanceof \WP_User ) {
					$type = Types::user();
				} else {
					$type = Types::comment_author();
				}
				return $type;
			},
		];

		parent::__construct( $config );

	}

	/**
	 * This defines the possible types that can be resolved by this union
	 *
	 * @return array|null An array of possible types that can be resolved by the union
	 */
	public function getPossibleTypes() {

		if ( null === self::$possible_types ) {
			self::$possible_types = [];
		}

		self::$possible_types = [
			'user'          => Types::user(),
			'commentAuthor' => Types::comment_author(),
		];

		return ! empty( self::$possible_types ) ? self::$possible_types : null;

	}

}
