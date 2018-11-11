<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'ImageSizeEnum', [
  'description' => __( 'Size of image', 'wp-graphql' ),
  'values' => array( 'THUMBNAIL', 'MEDIUM', 'LARGE', 'FULLSIZE' )
] );