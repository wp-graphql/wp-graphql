<?php
namespace DFM\WPGraphQL\Types;

use DFM\WPGraphQL\Types\AdLayerType;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;

class AdLayersType extends PostsType {

	public function getDescription() {
		return __( 'The base Ad Layers Type with info about the query and a list of queried items', 'wp-graphqhl' );
	}
}