---
uri: "/debugging/"
title: "Debugging"
---

On this page you will find information to help debug when things are not working as you might expect when using WPGraphQL for ACF v2.0+.

If, after trying suggestions documented on this page, you are still having problems, check out our [various channels for support](/support/).

## General WPGraphQL Debugging

For a broad understanding of WPGraphQL debugging, start with the [official WPGraphQL documentation](https://www.wpgraphql.com/docs/debugging). It provides a foundational overview that could be crucial for troubleshooting.

## Installation Issues

**Problem:** If you have tried to install and activate the plugin and are seeing errors such as: `Uncaught Error: Class 'WPGraphQL\Acf\ThirdParty' not found` or `WPGraphQL for Advanced Custom Fields v%s cannot load`

**Solution:** These errors typically indicate an incorrect installation. For a .zip download from GitHub, use the release artifacts available at [WPGraphQL for ACF GitHub Releases](https://github.com/wp-graphql/wp-graphql/releases). If you’ve cloned the repository, remember to execute `composer install` or `composer install --no-dev` to install necessary dependencies and the autoloader. Also, verify your WordPress and PHP versions to ensure compatibility.

## Performance Issues

Slowdowns after activating WPGraphQL for ACF? Here’s what to check:

### Updated Plugins

Ensure all plugins, not just those directly related to GraphQL, are updated to their latest versions.

### ACF Local JSON

Using [ACF Local JSON](https://www.advancedcustomfields.com/resources/local-json/) will reduce database queries and improve performance.

### Set GraphQL Types Manually

Bypass expensive logic by setting `graphql_types` manually, rather than relying on the plugin to map ACF Field Groups based on location rules.

## ACF Field Group isn’t showing in the Schema

If you’re not seeing your ACF Field Group in the Schema:

### Check the Schema

If you’re not seeing the ACF Field Group in the Schema where you’d expect, check the Schema to see if the ACF Field Group is registered at all.

Using the GraphiQL IDE, you can open the “Docs” browser and search for “AcfFieldGroup”.

This will return the “AcfFieldGroup” Interface Type and will list all of the GraphQL Object Types that implement the interface.

If you do not see an object type representing your ACF Field Group as a Type that implements the AcfFieldGroup Interface, then your field group has not been added to the GraphQL Schema.

### Field Group Settings

ACF Field Group settings have a direct impact on how your ACF Field Groups will map to the WPGraphQL Schema.

Below are some things to check for in regards to your ACF Field Group Settings.

#### Active?

Is your ACF Field Group active?

If your ACF Field Group is set to `active:false` this does not mean that the ACF Field Group will be excluded from the GraphQL Schema.

Inactive ACF Field Groups that are set to “Show in GraphQL” will not have a direct field added to the schema to access it, _but_ they will have a GraphQL Object Type registered to the Schema and can be referenced via [Clone](/field-types/clone-field/) fields.

#### Show in GraphQL

Is your ACF Field Group set to Show in GraphQL?

Whether your ACF Field Group is active or not, it needs to be set to “Show in GraphQL” in order to be registered to the Schema.

The `show_in_graphql` field on the ACF Field Group needs to be set to `true` in order for the ACF Field Group to be represented in the GraphQL Schema.

#### GraphQL Type Name

Does your ACF Field Group have a GraphQL Type Name set?

If your ACF Field Group is set to Show in GraphQL, but it does not have a GraphQL Type Name set, it will not properly map to the GraphQL Schema.

Make sure you have a GraphQL Type name set, and make sure it’s unique across the GraphQL Schema.

For example, do not name your ACF Field Group the same as an existing Type in the Schema such as “Post” or “Page”.

Also, if using ACF Options Pages or ACF Blocks, be sure to name your GraphQL Types unique in relation to those Types.

For example, if you have an ACF Options page with the “graphql\_type\_name” of “Settings” and then you create an ACF Field Group and also give it a “graphql\_type\_name” of “Settings” then both the Options Page and the Field Group will attempt to be added to the schema as “Settings” which will lead to unwanted behavior.

#### GraphQL Types / Location Rules

Double check your field group’s “graphql\_types” setting. Whatever types are defined here will be the types that have a field registered to them that provides access to the ACF Field Group.

If there are no “graphql\_types” defined, WPGraphQL for ACF might be able to map the field group to the Schema based on the field group’s location rules, but it’s a safer bet to use “graphql\_types” explicitly.

## ACF Field isn’t showing in the Schema

If your ACF Field Group is showing in the GraphQL Schema as an object type implementing the “AcfFieldGroup” Interface, but a particular field within the field group is not showing in the schema, here are some things to look for:

### Show in GraphQL

Is the field set to show in graphql? By default, if a field group is set to show in graphql, all fields of the group will also be shown in the GraphQL Schema, but individual fields can be set to `show_in_graphql: false` to be excluded from the Schema.

### Supported Field Types

Is the ACF Field Type a [supported field type](/field-types/)?

If you’re using a 3rd party ACF Extension that adds a custom ACF Field Type, it won’t be shown in the GraphQL Schema unless [support is added for the custom ACF Field type](/adding-support-for-3rd-party-acf-field-type/).

## Next Steps

This guide covers the some common steps for debugging WPGraphQL for ACF v2.0+. If you’re still encountering issues after following these steps, please refer to our [support page](/support/) for further assistance.
