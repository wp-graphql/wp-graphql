<?php
namespace DFM\WPGraphQL\Types\Shortcodes;

use Youshido\GraphQL\Config\Field\FieldConfig;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Field\AbstractField;
use Youshido\GraphQL\Type\Scalar\StringType;

class ShortcodeQueryType extends AbstractField  {

	protected $query_name = 'Shortcode';

	public function __construct( $args ) {

		$this->query_name = ! empty( $args['query_name'] ) ? $args['query_name'] : $this->query_name;

		$config = [
			'name' => $this->getName(),
			'type' => $this->getType(),
			'resolve' => [ $this, 'resolve' ]
		];

		parent::__construct( $config );

	}

	public function getName() {
		return $this->query_name;
	}

	public function getDescription() {
		return 'Goo';
	}

	public function getType() {
		return new StringType();
	}

	public function resolve( $value, array $args, ResolveInfo $info ) {
		return 'goo ';
	}

}