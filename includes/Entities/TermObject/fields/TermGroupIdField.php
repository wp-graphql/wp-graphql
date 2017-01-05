<?php
namespace DFM\WPGraphQL\Entities\TermObject\Fields;

use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\IntType;

/**
 * Class TermGroupIdField
 * @package DFM\WPGraphQL\Fields
 * @since 0.0.2
 */
class TermGroupIdField extends AbstractField {

	/**
	 * @return string
	 * @since 0.0.2
	 */
	public function getName() {
		return 'term_group_id';
	}

	/**
	 * @return IntType
	 * @since 0.0.2
	 */
	public function getType() {
		return new IntType();
	}

	/**
	 * @return mixed
	 * @since 0.0.1
	 */
	public function getDescription() {
		return __( 'The ID of the term group that this term object belongs to', 'wp-graphql' );
	}

	/**
	 * @param $value
	 * @param array $args
	 * @param ResolveInfo $info
	 *
	 * @return mixed
	 * @since 0.0.2
	 */
	public function resolve( $value, array $args, ResolveInfo $info ) {
		return absint( $value->term_group_id );
	}

}
