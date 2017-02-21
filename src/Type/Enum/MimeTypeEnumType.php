<?php
namespace WPGraphQL\Type\Enum;

use GraphQL\Type\Definition\EnumType;

class MimeTypeEnumType extends EnumType {

	private static $values;

	public function __construct() {

		$config = [
			'name'   => 'mimeType',
			'values' => self::values(),
		];

		parent::__construct( $config );

	}

	private static function values() {

		if ( null === self::$values ) {

			self::$values       = [];
			$allowed_mime_types = get_allowed_mime_types();

			if ( ! empty( $allowed_mime_types ) ) {
				foreach ( $allowed_mime_types as $mime_type ) {
					self::$values[] = [
						'name'  => strtoupper( preg_replace( '/[^A-Za-z0-9]/i', '_', $mime_type ) ),
						'value' => $mime_type,
					];
				}
			}
		}

		return ! empty( self::$values ) ? self::$values : null;

	}

}
