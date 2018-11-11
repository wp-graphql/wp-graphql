<?php
namespace WPGraphQL\Type;

register_graphql_enum_type( 'ArchiveGroupEnum', [
  'description' => __( 'Archive grouping types', 'wp-graphql' ),
  'values' => array( 'YEARLY', 'MONTHLY', 'DAILY', 'WEEKLY', 'POSTBYPOST', 'ALPHA' )
] );