<?php
namespace DFM\WPGraphQL\Queries\Posts;

use DFM\WPGraphQL\Queries\PostEntities\PostObjectQuery;
use DFM\WPGraphQL\Types\PostType;
use Youshido\GraphQL\Config\Field\FieldConfig;

class Query extends PostObjectQuery {

	public function getType() {
		return new PostType();
	}

}