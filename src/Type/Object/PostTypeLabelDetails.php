<?php

namespace WPGraphQL\Type\Object;

class PostTypeLabelDetails {
	public static function register_type() {
		register_graphql_object_type(
			'PostTypeLabelDetails',
			[
				'description' => __( 'Details for labels of the PostType', 'wp-graphql' ),
				'fields'      => [
					'name'                => [
						'type'        => 'String',
						'description' => __( 'General name for the post type, usually plural.', 'wp-graphql' ),
					],
					'singularName'        => [
						'type'        => 'String',
						'description' => __( 'Name for one object of this post type.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->singular_name ) ? $labels->singular_name : null;
						},
					],
					'addNew'              => [
						'type'        => 'String',
						'description' => __( 'Default is ‘Add New’ for both hierarchical and non-hierarchical types.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->add_new ) ? $labels->add_new : null;
						},
					],
					'addNewItem'          => [
						'type'        => 'String',
						'description' => __( 'Label for adding a new singular item.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->add_new_item ) ? $labels->add_new_item : null;
						},
					],
					'editItem'            => [
						'type'        => 'String',
						'description' => __( 'Label for editing a singular item.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->edit_item ) ? $labels->edit_item : null;
						},
					],
					'newItem'             => [
						'type'        => 'String',
						'description' => __( 'Label for the new item page title.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->new_item ) ? $labels->new_item : null;
						},
					],
					'viewItem'            => [
						'type'        => 'String',
						'description' => __( 'Label for viewing a singular item.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->view_item ) ? $labels->view_item : null;
						},
					],
					'viewItems'           => [
						'type'        => 'String',
						'description' => __( 'Label for viewing post type archives.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->view_items ) ? $labels->view_items : null;
						},
					],
					'searchItems'         => [
						'type'        => 'String',
						'description' => __( 'Label for searching plural items.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->search_items ) ? $labels->search_items : null;
						},
					],
					'notFound'            => [
						'type'        => 'String',
						'description' => __( 'Label used when no items are found.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->not_found ) ? $labels->not_found : null;
						},
					],
					'notFoundInTrash'     => [
						'type'        => 'String',
						'description' => __( 'Label used when no items are in the trash.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->not_found_in_trash ) ? $labels->not_found_in_trash : null;
						},
					],
					'parentItemColon'     => [
						'type'        => 'String',
						'description' => __( 'Label used to prefix parents of hierarchical items.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->parent_item_colon ) ? $labels->parent_item_colon : null;
						},
					],
					'allItems'            => [
						'type'        => 'String',
						'description' => __( 'Label to signify all items in a submenu link.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->all_items ) ? $labels->all_items : null;
						},
					],
					'archives'            => [
						'type'        => 'String',
						'description' => __( 'Label for archives in nav menus', 'wp-graphql' ),
					],
					'attributes'          => [
						'type'        => 'String',
						'description' => __( 'Label for the attributes meta box.', 'wp-graphql' ),
					],
					'insertIntoItem'      => [
						'type'        => 'String',
						'description' => __( 'Label for the media frame button.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->insert_into_item ) ? $labels->insert_into_item : null;
						},
					],
					'uploadedToThisItem'  => [
						'type'        => 'String',
						'description' => __( 'Label for the media frame filter.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->uploaded_to_this_item ) ? $labels->uploaded_to_this_item : null;
						},
					],
					'featuredImage'       => [
						'type'        => 'String',
						'description' => __( 'Label for the Featured Image meta box title.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->featured_image ) ? $labels->featured_image : null;
						},
					],
					'setFeaturedImage'    => [
						'type'        => 'String',
						'description' => __( 'Label for setting the featured image.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->set_featured_image ) ? $labels->set_featured_image : null;
						},
					],
					'removeFeaturedImage' => [
						'type'        => 'String',
						'description' => __( 'Label for removing the featured image.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->remove_featured_image ) ? $labels->remove_featured_image : null;
						},
					],
					'useFeaturedImage'    => [
						'type'        => 'String',
						'description' => __( 'Label in the media frame for using a featured image.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->use_featured_item ) ? $labels->use_featured_item : null;
						},
					],
					'menuName'            => [
						'type'        => 'String',
						'description' => __( 'Label for the menu name.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->menu_name ) ? $labels->menu_name : null;
						},
					],
					'filterItemsList'     => [
						'type'        => 'String',
						'description' => __( 'Label for the table views hidden heading.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->filter_items_list ) ? $labels->filter_items_list : null;
						},
					],
					'itemsListNavigation' => [
						'type'        => 'String',
						'description' => __( 'Label for the table pagination hidden heading.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->items_list_navigation ) ? $labels->items_list_navigation : null;
						},
					],
					'itemsList'           => [
						'type'        => 'String',
						'description' => __( 'Label for the table hidden heading.', 'wp-graphql' ),
						'resolve'     => function( $labels ) {
							return ! empty( $labels->items_list ) ? $labels->items_list : null;
						},
					],
				],
			]
		);

	}
}
