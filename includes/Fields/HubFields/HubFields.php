<?php
namespace DFM\WPGraphQL\Fields\HubFields;


use DFM\WPGraphQL\Fields\HubFields\HubSourceArticleFields;
use DFM\WPGraphQL\Fields\HubFields\HubSubscriptionFields;
use Youshido\GraphQL\Execution\ResolveInfo;
use Youshido\GraphQL\Type\ListType\ListType;
use Youshido\GraphQL\Type\Object\AbstractObjectType;
use Youshido\GraphQL\Type\Scalar\IntType;
use Youshido\GraphQL\Type\Scalar\StringType;

/**
 * Class HubFields
 * @package DFM\WPGraphQL\Types
 * @since 0.0.2
 */
class HubFields extends AbstractObjectType {

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getName() {
		return __( 'hub', 'wp-graphql' );
	}

	/**
	 * @return mixed
	 * @since 0.0.2
	 */
	public function getDescription() {
		return __( 'Details about the object in relation to the Hub', 'wp-graphql' );
	}

	/**
	 * @param \Youshido\GraphQL\Config\Object\ObjectTypeConfig $config
	 * @since 0.0.2
	 */
	public function build( $config ) {

		/**
		 * content_hub_post_id @deprecated before building the GraphQL API, but still in the DB so could be useful for introspection
		 * dfm_hub_post_id
		 * dfm_hub_source_article_meta
		 * dfm_hub_source_meta
		 * dfm_hub_subscriber
		 * dfm_hub_subscription
		 * dfm_hub_syndication_do_update
		 * dfm_hub_syndication_retries
		 * dfm_hub_syndication_status
		 * dfm_hub_syndication_version
		 */
		$fields = [

			/**
			 * content_hub_post_id
			 * @since 0.0.2
			 */
			'content_hub_post_id' => [
				'type' => new IntType(),
				'description' => __( 'The ID of the related Object in Content Hub', 'wp-graphql' ),
				'isDeprecated' => true,
				'deprecationReason' => __( 'Field used before the content hub was revamped during the BANG site launch. Fields might still exist in Denver Post / Twin Cities', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ){
					return absint( get_post_meta( $value->ID, 'content_hub_post_id', true ) );
				},
			],

			'id' => [
				'type' => new IntType(),
				'description' => __( 'The ID of the synced object in the hub' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return absint( get_post_meta( $value->ID, 'dfm_hub_post_id', true ) );
				}
			],

			'source_article' => [
				'type' => new HubSourceArticleFields(),
				'description' => __( 'Fields related to Hub such as source ids, hub ids, canonicals, etc. this only exists on an imported or subscribed post', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {

					$source_article = get_post_meta( $value->ID, 'dfm_hub_source_article_meta', true );
					return ! empty( $source_article ) ? $source_article : array();
				}
			],

			'source_meta' => [
				'type' => new HubSourceArticleFields(),
				'description' => __( 'Fields related to Hub such as source ids, hub ids, canonicals, etc. this only exists on an imported or subscribed post', 'wp-graphql' ),
				'isDeprecated' => true,
				'deprecationReason' => __( 'Replaced by "source_article"', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return get_post_meta( $value->ID, 'dfm_hub_source_meta', true );
				},
			],

			'subscribers' => [
				'type' => new ListType( new StringType() ),
				'description' => __( 'A list of domains that have subscribed to the article', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {

					$subscribers = get_post_meta( $value->ID, 'dfm_hub_subscribers', true );
					return ! empty( $subscribers ) ? $subscribers : array();
				}
			],

			'subscription' => [
				'type' => new HubSubscriptionFields(),
				'description' => __( 'Fields related to Hub subscriptions', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					$subscription = get_post_meta( $value->ID, 'dfm_hub_subscription', true );
					return ( ! empty( $subscription ) ) ? $subscription : array();
				}
			],

			'do_update' => [
				'type' => new IntType(),
				'description' => __( 'Timestamp for when the post was scheduled to sync', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return absint( get_post_meta( $value->ID, 'dfm_hub_syndication_do_update', true ) );
				}
			],

			'retries' => [
				'type' => new IntType(),
				'description' => __( 'Number of retries the object has attempted to sync', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return absint( get_post_meta( $value->ID, 'dfm_hub_syndication_retries', true ) );
				}
			],

			'syndication_status' => [
				'type' => new StringType(),
				'description' => __( 'The status of the object in regards to syncing with the hub', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return esc_html( get_post_meta( $value->ID, 'dfm_hub_syndication_status', true ) );
				}
			],

			'version' => [
				'type' => new IntType(),
				'description' => __( 'The syndication version', 'wp-graphql' ),
				'resolve' => function( $value, array $args, ResolveInfo $info ) {
					return absint( get_post_meta( $value->ID, 'dfm_hub_syndication_version', true ) );
				}
			],


		];

		/**
		 * addFields
		 * @since 0.0.2
		 */
		$config->addFields( $fields );

	}

}