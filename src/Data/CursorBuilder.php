<?php

namespace WPGraphQL\Data;

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

class CursorBuilder {

	public $fields;

	public $compare = null;

	public function __construct( $compare = '>') {
		$this->compare = $compare;
		$this->fields = [];
	}

	public function add_field( $key, $value, $type = null, $order = null ) {
		$this->fields[] = [
			'key' => $key,
			'value' => $value,
			'type' => $type,
			'order' => $order,
		];
	}

	public function has_fields() {
		return count( $this->fields ) > 0;
	}

	public function to_sql( $fields = null ) {
		if ( null === $fields ) {
			$fields = $this->fields;
		}

		if ( count( $fields ) === 0 ) {
			return '';
		}

		$field = $fields[0];

		$key = $field['key'];
		$value = $field['value'];
		$type = $field['type'];
		$order = $field['order'];

		$compare = $this->compare;

		if ( null !== $order ) {
			error_log("\n\nUSing custom order\n\n");
			$compare = 'DESC' === $order ? '<' : '>';
		}


		if ( 'ID' !== $type ) {
			$cast = $this->get_cast_for_type( $type );
			if ( 'CHAR' === $cast ) {
				$value = "'$value'";
			} else if ( $cast ) {
				$key = "CAST( $key as $cast )";
				$value = "CAST( '$value' as $cast )";
			}
		}

		if ( count( $fields ) === 1 ) {
			return " {$key} {$compare} {$value}";
		}

		$nest = $this->to_sql( \array_slice( $fields, 1 ) );
		return " {$key} {$compare}= {$value} AND ( {$key} {$compare} {$value} OR ( {$nest} ) ) ";
	}


	/**
	 * Copied from https://github.com/WordPress/WordPress/blob/c4f8bc468db56baa2a3bf917c99cdfd17c3391ce/wp-includes/class-wp-meta-query.php#L272-L296
	 *
	 * It's an intance method. No way to call it without creating the instance?
	 *
	 * Return the appropriate alias for the given meta type if applicable.
	 *
	 * @param string $type MySQL type to cast meta_value.
	 * @return string MySQL type.
	 */
	public function get_cast_for_type( $type = '' ) {
		if ( empty( $type ) ) {
			return 'CHAR';
		}
		$meta_type = strtoupper( $type );
		if ( ! preg_match( '/^(?:BINARY|CHAR|DATE|DATETIME|SIGNED|UNSIGNED|TIME|NUMERIC(?:\(\d+(?:,\s?\d+)?\))?|DECIMAL(?:\(\d+(?:,\s?\d+)?\))?)$/', $meta_type ) ) {
			return 'CHAR';
		}
		if ( 'NUMERIC' == $meta_type ) {
			$meta_type = 'SIGNED';
		}
		return $meta_type;
	}
}


// $ding = new CursorBuilder();
// $ding->add_field('c1', ':lrv1');
// $ding->add_field('c2', ':lrv2');
// $ding->add_field('c3', ':lrv3', 'NUMERIC');

// error_log("select * from " . $ding->to_sql());