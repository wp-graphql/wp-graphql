<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Add field to output URLs for Sitemap"
wordpressUri: "/recipes/add-field-to-output-urls-for-sitemap/"
wordpressId: "2504"
group: "Custom Fields"
summary: "The following code is an example of how you can create a field called allUrls that will output site URLs that could be used to generate a sitemap. The resolver uses mostly hard-coded data, but shows what a potential solu…"
---

The following code is an example of how you can create a field called allUrls that will output site URLs that could be used to generate a sitemap.

The resolver uses mostly hard-coded data, but shows what a potential solution could look like.

```
add_action( 'graphql_register_types', function() {

	register_graphql_field( 'RootQuery', 'allUrls', [
		'type' => [ 'list_of' => 'String' ],
		'description' => __( 'A list of all urls. Helpful for rendering sitemaps', 'your-textdomain' ),
		'resolve' => function() {

			// Start collecting all URLS
			$meta_urls = array( get_home_url() );

                        // Hardcoded array. You would need to fetch the URLs for all Posts
			$all_posts = [ 'site.com/post', 'site.com/post-2' ];

                        // Hardcoded array. You would need to fetch the URLs for all Terms
			$all_terms = [ 'site.com/tag', 'site.com/category' ];

			return array_merge($meta_urls, $all_posts, $all_terms);

		}
	]  );

});
```

You can then query this field using:

```
{
  allUrls
}
```

![Querying the allUrls field in GraphiQL](https://content.wpgraphql.com/wp-content/uploads/2020/10/AllUrlsField-1024x273.jpg)
