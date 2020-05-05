<?php

namespace WPGraphQL\Type\Object;

class AdminColorSchemePalette {
	public static function register_type() {
		register_graphql_object_type(
			'AdminColorScheme',
			[
				'description' => __( 'Each user profile has unique scheme styling. WordPress by default uses Admin Colors to style the user profile backend via the attached css file.', 'wp-graphql' ),
                'fields' => [
                    'name'          => [
                        'type'        => 'String',
                        'description' => __( 'Admin Color Scheme', 'wp-graphql' ),
                    ],
                    'colorsList'     => [
                        'type'        => [
                            'list_of' => 'String',
                        ],
                        'description' => __( 'Admin Color Scheme Palette', 'wp-graphql' ),
                        'resolve' => function( $admin_colors ) {
                            return ! empty( $admin_colors->colors ) ? $admin_colors->colors : null;
                        },
                    ],
                    'iconColorsList' => [
                        'type' => [
                            'list_of'  => 'String',
                        ],
                        'resolve' => function( $admin_colors  ) {
        
                            $colors = [];
                            if ( ! empty( $admin_colors->icon_colors ) ) {
                                foreach ( $admin_colors->icon_colors as $key => $color ) {
                                    $colors[] = $color;
                                }
                            }
        
                            return ! empty( $colors ) ? $colors : null;
                        },
        
                    ],
                ]
			]
        );
        register_graphql_object_type(
			'AdminIconColor',
			[
				'description' => __( 'Each user profile has unique scheme styling. WordPress by default uses Admin Icon Colors to style the icons via the attached css file.', 'wp-graphql' ),
				'fields'      => [
                    'base'        => [
                        'type'        => 'String',
                        'description' => __( 'Primary icon color', 'wp-graphql' ),
                    ],
                    'focus'       => [
                        'type'        => 'String',
                        'description' => __( 'Link icon color on focus.', 'wp-graphql' ),
                    ],
                    'current'     => [
                        'type'        => 'String',
                        'description' => __( 'Link icon color when active.', 'wp-graphql' ),
                    ],
                ],
			]
		);
	}
}
