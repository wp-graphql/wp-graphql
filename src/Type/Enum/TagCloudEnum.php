<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'TagCloudEnum', [
  'description' => __( 'Taxonomy of widget resource type', 'wp-graphql' ),
  'values' => array( 'POST_TAG', 'CATEGORY', 'LINK_CATEGORY' )
] );