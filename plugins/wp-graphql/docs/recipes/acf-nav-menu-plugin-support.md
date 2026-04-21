<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "ACF Nav Menu Plugin Support"
wordpressUri: "/recipes/acf-nav-menu-plugin-support/"
wordpressId: "2336"
group: "Uncategorized"
summary: "This adds support (native) for the ACF Nav Menu field plugin ( https://github.com/jgraup/advanced-custom-fields-nav-menu-field ). This also requires WPGraphQL for Advanced Custom Fields . add_filter( 'wpgraphql_acf_regis…"
---

This adds support (native) for the ACF Nav Menu field plugin ([https://github.com/jgraup/advanced-custom-fields-nav-menu-field](https://github.com/jgraup/advanced-custom-fields-nav-menu-field)). This also requires [WPGraphQL for Advanced Custom Fields](https://github.com/wp-graphql/wp-graphql-acf).

```
add_filter( 'wpgraphql_acf_register_graphql_field', function( $field_config, $type_name, $field_name, $config ) {
	if ( isset( $config['acf_field']['type'] ) && 'nav_menu' === $config['acf_field']['type'] ) {
		$field_config['type'] = 'Menu';
		$field_config['resolve'] = function( $root, $args, \WPGraphQL\AppContext $context, $info ) use ( $config ) {
			$menu_id = get_field( $config['acf_field']['key'], $root->databaseId );
			return ! empty( $menu_id ) ? $context->get_loader( 'term' )->load_deferred( $menu_id ) : null;
		};
	}
  return $field_config;
}, 10, 4 );
```

You can query for this:

```
{
  post(id: 1669, idType: DATABASE_ID) {
    id
    title
    content
    acfPostFieldGroup {
      menu {
        id
        name
      }
    }
  }
}
```

![ACF Nav Menu Plugin query in GraphiQL](https://content.wpgraphql.com/wp-content/uploads/2020/10/ACFNavMenu.png)
