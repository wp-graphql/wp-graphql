<?php
namespace WPGraphQL\Type;

use WPGraphQL\Data\DataSource;

/**
 * Defines a generic resolver function
 *
 * @return callable
 */
function resolve_field( $key, $default ) {
	return function( array $widget, $args, $context, $info ) use ( $key, $default ) {
		return ( ! empty( $widget[ $key ] ) ) ? $widget[ $key ] : $default;
	};
}

/**
 * Defines a generic title field
 *
 * @return array
 */
function title_field( $default ) {
	return array(
		'type' => 'String',
		'description' => __( 'Display name of widget', 'wp-graphql' ),
		'resolve' => resolve_field( 'title', $default ),
	);
}

/**
 * Registers ArchivesWidget
 */
register_graphql_object_type( 'ArchivesWidget', [
	'description' 	=> __( 'An archives widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'     => title_field('Archives'),
		'count'     => [
			'type'        => 'Boolean',
			'description' => __( 'Show posts count', 'wp-graphql' ),
			'resolve'     => resolve_field( 'count', false )
		],
		'dropdown'  => [
			'type'        => 'Boolean',
			'description' => __( 'Display as dropdown', 'wp-graphql' ),
			'resolve'     => resolve_field( 'dropdown', false )
		],
		'urls'      => [
			'type'        => ['list_of' => 'String'],
			'args'        => [
				'group' => [ 
					'type'        => 'ArchiveGroupEnum',
					'description' => __( 'How archives should be group', 'wp-graphql' ),
					'defaultValue'=> 'MONTHLY',
				],
				'absolute'  => [
					'type'        => 'Boolean',
					'description' => __( 'Absolute URL?', 'wp-graphql' ),
				] 
			],
			'description' => __( 'List of relative urls to archive listing', 'wp-graphql' ),
			'resolve'     => function( $root, $args ) {
				$absolute = ( ! empty( $args['absolute'] ) ) ? $args['absolute'] : false;

				$urls = DataSource::resolve_archive_urls( $args['group'], $absolute );
				
				return ! empty ( $urls ) ? $urls : null;
			}
		]
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'archives';
	}
] );

/**
 * Registers AudioWidget
 */
register_graphql_object_type( 'AudioWidget', [
	'description' 	=> __( 'An audio widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'   => title_field('Audio'),
		'audio'   => [
			'type'        => 'Int',
			'description' => __( 'Widget audio file data object', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget['attachment_id'] ) ) ? $widget['attachment_id'] : null;
			}
		],
		'preload' => [
			'type'        => 'PreloadEnum',
			'description' => __( 'Sort style of widget', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'preload' ] ) ) ? $widget[ 'preload' ] : null;
			}
		],
		'loop'    => [
			'type'        => 'Boolean',
			'description' => __( 'Play repeatly', 'wp-graphql' ),
			'resolve'     => resolve_field( 'loop', false )
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'media_audio';
	}
] );

/**
 * Registers CalendarWidget
 */
register_graphql_object_type( 'CalendarWidget', [
	'description'  	=> __( 'A calendar widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'       	=> [ 'title' => title_field('Calendar') ],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'calendar';
	}
] );

/**
 * Registers CategoriesWidget
 */
register_graphql_object_type( 'CategoriesWidget', [
	'description' 	=> __( 'A categories widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'         => title_field('Categories'),
		'count'         => [
			'type'        => 'Boolean',
			'description' => __( 'Show posts count', 'wp-graphql' ),
			'resolve'     => resolve_field( 'count', false )
		],
		'dropdown'      => [
			'type'        => 'Boolean',
			'description' => __( 'Display as dropdown', 'wp-graphql' ),
			'resolve'     => resolve_field( 'dropdown', false )
		],
		'hierarchical'  => [
			'type'        => 'Boolean',
			'description' => __( 'Show hierachy', 'wp-graphql' ),
			'resolve'     => resolve_field( 'hierarchical', false )
		]
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'categories';
	}
] );

/**
 * Registers CustomHTMLWidget
 */
register_graphql_object_type( 'CustomHTMLWidget', [
	'description' 	=> __( 'A custom html widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'     => title_field('Custom HTML'),
		'content'     => [
			'type'        => 'String',
			'description' => __( 'Content of custom html widget', 'wp-graphql' ),
			'resolve'     => resolve_field( 'content', '' )
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'custom_html';
	}
] );

/**
 * Registers GalleryWidget
 */
register_graphql_object_type( 'GalleryWidget', [
	'description'  	=> __( 'A gallery widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'       	=> [
		'title'         => title_field('Gallery'),
		'columns'       => [
			'type'        => 'Int',
			'description' => __( 'Number of columns in gallery showcase', 'wp-graphql' ),
			'resolve'     => resolve_field( 'columns', 3 ),
		],
		'size'          => [
			'type'        => 'ImageSizeEnum',
			'description' => __( 'Display size of gallery images', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'size' ] ) ) ? $widget[ 'size' ] : null;
			},
		],
		'linkType'      => [
			'type'        => 'LinkToEnum',
			'description' => __( 'Link types of gallery images', 'wp-graphql'),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'link_type' ] ) ) ? $widget[ 'link_type' ] : null;
			},
		],
		'orderbyRandom' => [
			'type'        => 'Boolean',
			'description' => __( 'Random Order', 'wp-graphql'),
			'resolve'     => resolve_field( 'orderby_random', false ),
		],
		'images'        => [
			'type'        => ['list_of' => 'Int'],
			'description' => __( 'WP IDs of image attachment object', 'wp-graphql'),
			'resolve'     => function( array $widget ) {
				if ( ! empty( $widget['ids'] ) && is_array( $widget['ids'] ) ) {
				return $widget['ids'];
				}
				return null;
			}
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'media_gallery';
	}
] );

/**
 * Registers ImageWidget
 */
register_graphql_object_type( 'ImageWidget', [
	'description' 	=> __( 'A image widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title' => title_field('Image'),
		'image' => [
			'type'        => 'Int',
			'description' => __( 'Widget audio file data object', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget['attachment_id'] ) ) ? $widget['attachment_id'] : null;
			}
		],
		'linkType' => [
			'type'        => 'LinkToEnum',
			'description' => __( 'Link types of images', 'wp-graphql'),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'link_type' ] ) ) ? $widget[ 'link_type' ] : null;
			},
		],
		'linkUrl' => [
			'type'        => 'String',
			'description' => __( 'Url of image link', 'wp-graphql' ),
			'resolve'     => resolve_field( 'link_url', '' ),
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'media_image';
	}
] );

/**
 * Registers MetaWidget
 */
register_graphql_object_type( 'MetaWidget', [
	'description' 	=> __( 'A meta widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [ 'title' => title_field('Meta') ],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'meta';
	}
] );

/**
 * Registers NavMenuWidget
 */
register_graphql_object_type( 'NavMenuWidget', [
	'description' 	=> __( 'A navigation menu widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'       	=> [
		'title' => title_field('Navigation'),
		'menu'  => [
			'type'        => 'Int',
			'description' => __( 'Widget navigation menu', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget['nav_menu'] ) ) ? $widget['nav_menu'] : null;
			}
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'nav_menu';
	}
] );

/**
 * Registers PagesWidget
 */
register_graphql_object_type( 'PagesWidget', [
	'description' 	=> __( 'A pages widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'       	=> [
		'title'   => title_field('Pages'),
		'sortby' => [
			'type'        => 'SortByEnum',
			'description' => __( 'Sort style of widget', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'sortby' ] ) ) ? $widget[ 'sortby' ] : null;
			}
		],
		'exclude' => [
			'type'        => ['list_of' => 'Int'],
			'description' => __( 'WP ID of pages excluding from widget display', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'exclude' ] ) ) ? explode(',', $widget[ 'exclude' ] ) : null;
			}
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'pages';
	}
] );

/**
 * Registers RecentCommentsWidget
 */
register_graphql_object_type( 'RecentCommentsWidget', [
	'description' 	=> __( 'A recent comments widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'               => title_field('Recent Comments'),
		'commentsPerDisplay'  => [
			'type'        => 'Int',
			'description' => __( 'Number of comments to display at one time', 'wp-graphql' ),
			'resolve'     => resolve_field( 'number', 5 ),
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'recent-comments';
	}
] );

/**
 * Registers RecentPostsWidget
 */
register_graphql_object_type( 'RecentPostsWidget', [
	'description' 	=> __( 'A recent posts widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'           => title_field('Recent Posts'),
		'postsPerDisplay' => [
			'type'        => 'Int',
			'description' => __( 'Number of posts to display at one time', 'wp-graphql' ),
			'resolve'     => resolve_field( 'number', 5 ),
		],
		'showDate'        => [
			'type'        => 'Boolean',
			'description' => __( 'Show post date', 'wp-graphql' ),
			'resolve'     => resolve_field( 'show_date', false )
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'recent-posts';
	}
] );

/**
 * Registers RSSWidget
 */
register_graphql_object_type( 'RSSWidget', [
	'description' 	=> __( 'A rss feed widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'           => title_field('RSS'),
		'url'             => [
			'type'        => 'String',
			'description' => __( 'Url of RSS/Atom feed', 'wp-graphql' ),
			'resolve'     => resolve_field( 'url', '' ),
		],
		'itemsPerDisplay' => [
			'type'        => 'Int',
			'description' => __( 'Number of items to display at one time', 'wp-graphql' ),
			'resolve'     => resolve_field( 'item', 10 ),
		],
		'error'           => [
			'type'        => 'Boolean',
			'description' => __( 'RSS url invalid', 'wp-graphql' ),
			'resolve'     => resolve_field( 'error', false )
		],
		'showSummary'     => [
			'type'        => 'Boolean',
			'description' => __( 'Show item summary', 'wp-graphql' ),
			'resolve'     => resolve_field( 'show_summary', false )
		],
		'showAuthor'      => [
			'type'        => 'Boolean',
			'description' => __( 'Show item author', 'wp-graphql' ),
			'resolve'     => resolve_field( 'show_author', false )
		],
		'showDate'        => [
			'type'        => 'Boolean',
			'description' => __( 'Show item date', 'wp-graphql' ),
			'resolve'     => resolve_field( 'show_date', true )
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'rss';
	}
] );

/**
 * Registers SearchWidget
 */
register_graphql_object_type( 'SearchWidget', [
	'description' 	=> __( 'A search widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [ 'title' => title_field('Search') ],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'search';
	}
] );

/**
 * Registers TagCloudWidget
 */
register_graphql_object_type( 'TagCloudWidget', [
	'description' 	=> __( 'A tag cloud widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'     => title_field('Tag Cloud'),
		'showCount' => [
			'type'        => 'Boolean',
			'description' => __( 'Show tag count', 'wp-graphql' ),
			'resolve'     => resolve_field( 'count', true )
		],
		'taxonomy'  => [
			'type'        => 'TagCloudEnum',
			'description' => __( 'Widget taxonomy type', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'taxonomy' ] ) ) ? $widget[ 'taxonomy' ] : null;
			}
		],
		'tags'      => [
			'type'        => ['list_of' => 'ID'],
			'args'        => [
				'orderbyName'  => [
					'type'        => 'Boolean',
					'description' => __( 'Sort by name', 'wp-graphql' ),
				],
			],
			'description' => __( 'Widget taxonomy type', 'wp-graphql' ),
			'resolve'     => function( array $widget, $args ) {
				$orderby_name = ( ! empty( $args['orderbyName'] ) ) ? $args['orderbyName'] : false;

				if( ! empty( $widget[ 'taxonomy' ] ) ) {
					$tags = DataSource::resolve_tag_cloud( $widget[ 'taxonomy' ], $orderby_name );
				} else {
					$tags = DataSource::resolve_tag_cloud( 'post_tag', $orderby_name );
				}

				return ! empty( $tags ) ? $tags : null;
			}
		],
  	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'tag_cloud';
	}
] );

/**
 * Registers TextWidget
 */
register_graphql_object_type( 'TextWidget', [
	'description' 	=> __( 'A text widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'       => title_field('Text'),
		'text'        => [
			'type'        => 'String',
			'description' => __( 'Text content of widget', 'wp-graphql' ),
			'resolve'     => resolve_field( 'text', '' ),
		],
		'filterText'  => [
			'type'        => 'Boolean',
			'description' => __( 'Filter text content', 'wp-graphql' ),
			'resolve'     => resolve_field( 'filter', true )
		],
		'visual'      => [
			'type'        => 'Boolean',
			'resolve'     => resolve_field( 'visual', true )
		]
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'text';
	}
] );

/**
 * Registers VideoWidget
 */
register_graphql_object_type( 'VideoWidget', [
	'description' 	=> __( 'A video widget object', 'wp-graphql' ),
	'interfaces' 	=> [ 'WidgetInterface' ],
	'fields'      	=> [
		'title'   => title_field('Video'),
		'video'   => [
			'type'        => 'Int',
			'description' => __( 'Widget video file data object', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'attachment_id' ] ) ) ? $widget['attachment_id'] : null;
			}
		],
		'preload' => [
			'type'        => 'PreloadEnum',
			'description' => __( 'Sort style of widget', 'wp-graphql' ),
			'resolve'     => function( array $widget ) {
				return ( ! empty( $widget[ 'preload' ] ) ) ? $widget[ 'preload' ] : null;
			}
		],
		'loop'    => [
			'type'        => 'Boolean',
			'description' => __( 'Play repeatly', 'wp-graphql' ),
			'resolve'     => resolve_field( 'loop', false )
		],
	],
	'isTypeOf' => function( array $widget ) {
		return $widget[ 'type' ] === 'media_video';
	}
] );

