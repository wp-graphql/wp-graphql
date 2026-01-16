---
uri: "/docs/intro-to-wordpress/"
title: "Intro to WordPress"
---

This guide will be most useful for developers with little to no WordPress experience. This is not intended to be the most comprehensive guide to WordPress in the world, but is intended to provide some insight into how WordPress works and resources to learn more about WordPress.

## Getting started with WordPress

This section is intended to help you get started with WordPress.

### Setting up WordPress on your computer

One of the first things you might want to do when exploring WordPress, is to set up a WordPress site on your personal computer.

There are many ways to do this and you can research and find alternatives you might prefer, but I will hands-down recommend using a tool called [LocalWP](https://localwp.com/). It's a desktop application that allows you to create new WordPress sites on your computer with the click of a few buttons. It takes care of configuring PHP, MySQL and basic configuration of WordPress to connect to the database. It provides one-click support for enabling XDebug, allows access to your WordPress site using [WP-CLI](https://wp-cli.org/), and more.

> Speaking of [WP-CLI](https://wp-cli.org/), this is a gem of a tool you should know about when working with WordPress. You can do a lot of things with WordPress from the command line using WP-CLI. LocalWP enables this for you, but if you setup WordPress on your own, you would need [to install WP-CLI](https://wp-cli.org/#installing) on your own.

It's truly fantastic and I recommend you give it a shot. I have no affiliation with the makers of the product, I just love using it. And, it's free. Seriously, give it a shot: https://localwp.com.

If you're not sold, here are some alternatives for quickly spinning up a local environment:

- [Lando](https://docs.lando.dev/config/wordpress.html)
- [MAMP](https://codex.wordpress.org/Installing_WordPress_Locally_on_Your_Mac_With_MAMP)
- [XAMP](https://themeisle.com/blog/install-xampp-and-wordpress-locally/)
- [Set it all up on your own](https://coolestguidesontheplanet.com/fastest-way-to-install-wordpress-on-osx-10-6/)

## What kind of sites can I make with WordPress

WordPress isn't just for blogs, like you may have heard. WordPress is a full-fledged CMS that can be used to manage all sorts of data and power various types of applications. Not only is WordPress the [most widely used CMS](https://w3techs.com/technologies/history_overview/content_management) in the world, according to [Statista.com](https://www.statista.com/statistics/710207/worldwide-ecommerce-platforms-market-share/#:~:text=WooCommerce%20was%20the%20worldwide%20leading,market%20share%20of%2028.24%20percent.), WordPress (via the [WooCommerce](https://woocommerce.com/) plugin) powers more e-commerce sites than any other e-commerce platform in the world!

WordPress has a robust [Plugin](https://wordpress.org/plugins/) and [Theme](https://wordpress.org/theme) ecosystem, allowing users and developers to customize how it works with ease.

With WPGraphQL, you can make the same kinds of sites, but using alternative front-ends. Your data can now be accessed from outside the WordPress theme layer.

- [Learn how to build a Gatsby site using WordPress and WPGraphQL](https://codeytek.com/using-gatsby-source-wordpress-experimental-to-create-gatsby-site/)
- [Learn how to build a NextJS site using WordPress and WPGraphQL](https://dev.to/kendalmintcode/configuring-wordpress-as-a-headless-cms-with-next-js-3p1o)
- [Learn how to build a Gridsome site using WordPress and WPGraphQL](https://cunisinc.com/creating-websites-fast-with-vue-js-and-gridsome/)
- [Learn how to build a website with Create React App, WordPress and WPGraphQL](/2019/01/10/build-an-app-using-react-and-the-graphql-plugin-for-wordpress-in-15mins/)

### Overview of WordPress data types

WordPress ships with a number of data types you out of the box, and APIs to allow you to register new types of data to manage.

#### Posts, Pages and Custom Post Types

At the heart of WordPress are "Posts". WordPress was originally created to be a blogging system, and thus Posts were the main focus. As WordPress evolved, "Pages" were added as another type of data to manage, and soon after an API to register "Custom Post Types" emerged, and users can now create custom Types to manage data. You could register a post type to manage Houses for your real-estate site, or Cars for an auto-broker. Really, just about any type of data you need to manage, you can manage with WordPress.

You can either register Custom Post Types using code, or you can create new Custom Post Types from your WordPress dashboard without writing a line of [code](https://developer.wordpress.org/reference/functions/register_post_type/) by using a plugin such as [Custom Post Type UI](https://wordpress.org/plugins/custom-post-type-ui/).

> If you use Custom Post Type UI, don't forget the [WPGraphQL for Custom Post Type UI](https://github.com/wp-graphql/wp-graphql-custom-post-type-ui) extension to use your custom data with WPGraphQL!

- [Learn how to use GraphQL to interact with Posts and Pages](/docs/posts-and-pages/)
- [Learn how to use GraphQL with Custom Post Types](/docs/custom-post-types/)

#### Categories, Tags and Custom Taxonomies

Categories and Tags are built-in Taxonomies, or types of data that allow other data (posts and custom post types) to be grouped and organized.

When WordPress was started as a blogging platform, it supported Categories and Tags as a way to group Blog Posts. WordPress has an API to register Custom Taxonomies so that any Custom Post Type can be grouped in various ways. You can register Custom Taxonomies with [code](https://developer.wordpress.org/reference/functions/register_taxonomy/), or you can also register with a plugin like [Custom Post Type UI](https://wordpress.org/plugins/custom-post-type-ui/).

- [Learn how to use GraphQL to interact with Categories and Tags](/docs/categories-and-tags/)
- [Learn how to use GraphQL with Custom Taxonomies](/docs/custom-taxonomies/)

#### Media

WordPress includes a Media library where Images, Video, Audio and other forms of media can be uploaded and managed.

- [Learn how to use GraphQL to interact with Media Items](/docs/media/)

#### Nav Menus

WordPress includes a Navigation Menu manager that allows site administrators to manage navigation menus for websites using a drag and drop interface.

- [Learn how to use GraphQL to interact with Menus and Menu Items](/docs/menus/)

#### Users

WordPress has includes fully-featured user management with a full [Roles and Capabilities](https://wordpress.org/support/article/roles-and-capabilities/) system, so you can limit users access to granular parts of the WordPress application. You can configure your WordPress site to allow users to be able to register to the site freely, or restrict access to only specific users you've added to the system.

User Roles and Capabilities can be customized using [code](https://developer.wordpress.org/reference/functions/add_role/), or by using plugins such as [Members](https://wordpress.org/plugins/members/), which provide user interfaces to finely control user roles and capabilities.

- [Learn how to use GraphQL to interact with users](/docs/users/)

#### Comments

WordPress includes a commenting system, which allows users to submit comments on Posts, Pages or Custom Post Types that have comments enabled. Site administrators can moderate comments from the WordPress dashboard by approving them, deleting them, marking them as spam or replying to them.

- [Learn how to use GraphQL to get comments](/docs/comments/)

## What are WordPress Plugins?

WordPress plugins are code that you can install to your WordPress site to add new features and functionality, similar to how you might install an App on your smartphone to give your phone new features and functionality.

The WordPress plugin ecosystem is booming, with more than 48,000 free plugins available on the [WordPress.org plugin directory](https://wordpress.org/plugins/). Thousands more WordPress plugins exist on other sources such as Github, as well as various companies and marketplaces selling premium WordPress plugins.

### How do Plugins Work with decoupled WordPress?

Many WordPress plugins were created with the assumption that WordPress is the CMS as well as the presentation layer, but that's not always the case today. With the rise of decoupled WordPress, it's common for WordPress to be used as a CMS, but *not* be used for its theme layer.

WPGraphQL has an extendable API which allows for WordPress plugins to extend the GraphQL Schema so their custom data can be used in decoupled applications via GraphQL Queries and Mutations.

Many popular WordPress plugins already have extension plugins that provide WPGraphQL Support:

- **[WPGraphQL for Advanced Custom Fields](/extenstion-plugins/wpgraphql-for-advanced-custom-fields/)**: Allows you to interact with data managed by Advanced Custom Fields using GraphQL Queries
- **[WPGraphQL for Yoast SEO](/extenstion-plugins/wpgraphql-for-yoast-seo/)**: Allows you to interact with Yoast SEO data using GraphQL Queries
- **[WPGraphQL for WooCommerce](/extenstion-plugins/wpgraphql-for-woocommerce/)**: Allows you to interact with WooCommerce data using GraphQL Queries and Mutations
- [... and so many more WPGraphQL Extensions](/extensions)

If you're a plugin author looking to add WPGraphQL support to your plugin, or you're using a WordPress plugin that doesn't have built-in support for WPGraphQL already and you want to add support, check out the following resources:

- [Build your first WPGraphQL Extension](/docs/build-your-first-wpgraphql-extension/)
- [Developer Reference](/developer-reference)
