<!--
Migrated from WordPress (CodeSnippet).
Edit freely. Re-run migrate with --force=true to overwrite this file.
-->
---
title: "Query the Homepage"
wordpressUri: "/recipes/query-the-homepage/"
wordpressId: "7883"
group: "Queries"
summary: "In WordPress, the homepage can be a Page or an archive of posts of the Post post_type (which is represented by WPGraphQL as a &#8220;ContentType&#8221; node). This query allows you to query the homepage, and specify what…"
---

In WordPress, the homepage can be a Page or an archive of posts of the Post post\_type (which is represented by WPGraphQL as a “ContentType” node).  
  
This query allows you to query the homepage, and specify what data you want in response if the homepage is a page, or if the homepage is a `ContentType` node.

```
{
  nodeByUri(uri: "/") {
    __typename
    ... on ContentType {
      id
      name
    }
    ... on Page {
      id
      title
    }
  }
}
```

If the homepage were set to a Page, like so:

![](https://content.wpgraphql.com/wp-content/uploads/2020/12/Screen-Shot-2020-12-16-at-1.36.18-PM.png)

Then a Page would be returned in the Query Results, like so:

![](https://content.wpgraphql.com/wp-content/uploads/2020/12/Screen-Shot-2020-12-16-at-1.36.28-PM.png)

But if the homepage were set to be the Posts page:

![](https://content.wpgraphql.com/wp-content/uploads/2020/12/Screen-Shot-2020-12-16-at-1.35.11-PM.png)

Then the results would return a ContentType node, like so:

![](https://content.wpgraphql.com/wp-content/uploads/2020/12/Screen-Shot-2020-12-16-at-1.35.49-PM-1024x593.png)
