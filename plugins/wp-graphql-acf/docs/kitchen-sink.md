---
uri: "/kitchen-sink/"
title: "Kitchen Sink"
---

The saying “Everything but the Kitchen Sink” is a [phrase that originated in the early 1900s](https://www.theidioms.com/everything-but-the-kitchen-sink/) to describe “almost everything that one can think of”.  
  
This page is configured to have just about every [ACF field type](/field-types/) associated with it, and data saved to those fields, so that we can execute GraphQL queries against this page to demonstrate functionality of the WPGraphQL for ACF plugin.

## “Kitchen Sink” Field Groups

This page has the following ACF Field Groups associated with it:

-   ACF Free Kitchen Sink ([download the JSON export](field-groups/kitchen-sink-acf-free.json))
    -   This Field Group has a field of each field-type provided by the [FREE version of ACF](https://wordpress.org/plugins/advanced-custom-fields/)
    -   Requires ACF Free to be active
-   ACF PRO Kitchen Sink ([download the JSON export](field-groups/kitchen-sink-acf-pro.json))
    -   This field Group has a field of each field-type provided by the [PRO version of ACF](https://www.advancedcustomfields.com/pro/)
    -   Requires ACF PRO to be active
-   ACF Extended Free Kitchen Sink ([download the JSON export](field-groups/kitchen-sink-acf-extended-free.json))
    -   This field Group has a field of each field-type provided by the [FREE version of ACF](https://wordpress.org/plugins/acf-extended/) Extended
    -   Requires ACF PRO and ACF Extended FREE to be active
-   ACF Extended PRO Kitchen Sink ([download the JSON export](field-groups/kitchen-sink-acf-extended-pro.json))
    -   This field Group has a field of each field-type provided by the [PRO version of ACF Extended](https://www.acf-extended.com/pro)
    -   Requires ACF PRO and ACF Extended PRO to be active

## Using the Field Groups in your own environment

The [Field Type documentation](/field-types/) throughout the site will demonstrate using GraphQL queries that query this this page as an example of how to use GraphQL to query ACF Fields of various field types.

If you want to setup the same ACF Field Groups in your environment, you can download the JSON exports above and import them to your WordPress install using the Advanced Custom Fields importer under the “ACF > Tools” menu.

**Note**: you will need the appropriate dependencies (ACF PRO, ACF Extended, ACF Extended Pro) to use the relevant field groups.
