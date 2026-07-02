<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Add Primary Category field for The SEO Framework plugin"
wordpressUri: "/recipes/add-primary-category-field-for-the-seo-framework-plugin/"
wordpressId: "2356"
group: "Custom Fields"
summary: "The following adds a field called primaryCat field when using The SEO Framework WordPress Plugin add_action( 'init', function() { register_graphql_connection([ 'fromType' => 'Post', 'toType' => 'Category', 'fromFieldName…"
---

The following adds a field called `primaryCat` field when using [The SEO Framework](https://wordpress.org/plugins/autodescription/) WordPress Plugin

```
add_action( 'init', function() {

	register_graphql_connection([
		'fromType' => 'Post',
		'toType' => 'Category',
		'fromFieldName' => 'primaryCat',
		'oneToOne' => true,
		'resolve' => function( \WPGraphQL\Model\Post $post, $args, $context, $info ) {

			$primary_term = null;

			if ( function_exists( 'the_seo_framework' ) ) {
				$primary_term = the_seo_framework()->get_primary_term( $post->ID, 'category' );
			}

			// If there's no primary term from the SEO Framework, get the first category assigned
			if ( empty( $primary_term ) ) {
				$terms = get_the_terms( $post->ID, 'category' );
				if ( ! empty( $terms ) ) {
					$primary_term = $terms[0]->term_id;
				}
			}

			// If there's no primary term, return null for the connection
			if ( empty( $primary_term ) ) {
				return null;
			}

			$resolver = new \WPGraphQL\Data\Connection\TermObjectConnectionResolver( $post, $args, $context, $info, 'category' );
			$resolver->set_query_arg( 'include', absint( $primary_term ) );
			return $resolver->one_to_one()->get_connection();

		}
	]);

} );
```
