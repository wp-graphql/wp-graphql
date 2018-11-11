<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'SortByEnum', [
  'description' => __( 'Sorting order of widget resource type', 'wp-graphql' ),
  'values' => array( 'MENU_ORDER', 'POST_TITLE', 'ID' )
] );