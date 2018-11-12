<?php
namespace WPGraphQL\Type;

use GraphQLRelay\Relay;
use WPGraphQL\Data\DataSource;
use WPGraphQL\TypeRegistry;

$widget_types = [
	'archives'        => 'ArchivesWidget',
	'media_audio'     => 'AudioWidget',
	'calendar'        => 'CalendarWidget',
	'categories'      => 'CategoriesWidget',
	'custom_html'     => 'CustomHTMLWidget',
	'media_gallery'   => 'GalleryWidget',
	'media_image'     => 'ImageWidget',
	'meta'            => 'MetaWidget',
	'nav_menu'        => 'NavMenuWidget',
	'pages'           => 'PagesWidget',
	'recent-comments' => 'RecentCommentsWidget',
	'recent-posts'    => 'RecentPostsWidget',
	'rss'             => 'RSSWidget',
	'search'          => 'SearchWidget',
	'tag_cloud'       => 'TagCloudWidget',
	'text'            => 'TextWidget',
	'media_video'     => 'VideoWidget',
];

register_graphql_interface_type( 'WidgetInterface', [
	'description' => __( 'A widget object', 'wp-graphql' ),
	'fields'		=> [
		'id'        => [
			'type'    => ['non_null' => 'ID'],
			'description' => __( 'Global ID', 'wp-graphql' ),
			'resolve' => function( array $widget, $args, $context, $info ) {
				return ( ! empty( $widget ) && ! empty( $widget[ 'id' ] ) ) ?
					Relay::toGlobalId( 'widget', $widget[ 'id' ] ) :
					null;
			},
		],
		'widgetId'  => [
			'type'    => 'String',
			'description' => __( 'WP widget instance ID', 'wp-graphql' ),
			'resolve' => function( array $widget, $args, $context, $info ) {
				return ( ! empty( $widget[ 'id' ] ) ) ? $widget[ 'id' ] : null;
			},
		],
		'name'      => [
			'type'    => 'String',
			'description' => __( 'Name of widget', 'wp-graphql' ),
			'resolve' => function( array $widget, $args, $context, $info ) {
				return ( ! empty( $widget[ 'name' ] ) ) ? $widget[ 'name' ] : null;
			},
		],
		'basename'  => [
			'type'    => 'String',
			'description' => __( 'WP widget instance ID basename', 'wp-graphql' ),
			'resolve' => function( array $widget, $args, $context, $info ) {
				return ( ! empty( $widget[ 'type' ] ) ) ? $widget[ 'type' ] : null;
			},
		],
	],
	'resolveType' => function ( $source ) use ( $widget_types ) {
		$types = apply_filters( 'graphql_widget_interface_types', $widget_types );
		if( ! empty( $types[ $source[ 'type' ] ] ) ) {
			return TypeRegistry::get_type( $types[ $source[ 'type' ] ] );
		} else {  
			return self;
		}
	},
] );

register_graphql_field( 'RootQuery', 'widget', [
	'type' 			=> 'WidgetInterface',
	'description' 	=> __( 'A WordPress widget', 'wp-graphql' ),
	'args' => [
		'id' => [ 'type' => ['non_null' => 'ID'] ],
	],
	'resolve' 		=> function( $source, $args, $context, $info ) {
		$id_components = Relay::fromGlobalId( $args['id'] );

		return DataSource::resolve_widget( $id_components['id'] );
	},
] );

register_graphql_field( 'RootQuery', 'widgetBy', [
	'type' 			=> 'WidgetInterface',
	'description' 	=> __( 'WordPress widget object by widget name or widget ID', 'wp-graphql' ),
	'args' => [
		'id' 	=> [ 'type' => 'String' ],
		'name' 	=> [ 'type' => 'String' ],
	],
	'resolve' 		=> function( $source, array $args, $context, $info ) {
		$widget = null;
		
		if( ! empty( $args[ 'id' ] ) ) {
			$widget = DataSource::resolve_widget( $args[ 'id' ] );
		}
		if ( ! empty( $args[ 'name' ] ) ) {
			$widget = DataSource::resolve_widget( $args[ 'name' ], 'name' );
		}

		return $widget;
	},
] );