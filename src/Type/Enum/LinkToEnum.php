<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'LinkToEnum', [
  'description' => __( 'Destination type of link', 'wp-graphql' ),
  'values' => array( 'NONE', 'POST', 'FILE', 'CUSTOM' )
] );