---
uri: "/docs/hierarchical-data/"
title: "Hierarchical Data"
---

WordPress contains several types of hierarchical data structures. This guide explains how to work with hierarchical data in WPGraphQL and how to transform flat data into nested structures.

## Types of Hierarchical Data

WordPress includes several types of hierarchical data:

- Pages and other hierarchical post types
- Categories and other hierarchical taxonomies
- Navigation Menus and Menu Items
- Comments and Comment Replies
- Custom hierarchical data structures

## Default Output Format

> [!NOTE]
> By default, WPGraphQL returns hierarchical data as a flat list. This is intentional and provides more flexibility for data manipulation on the client side.

For example, a query for menu items might return:

```graphql
query MenuItems {
  menuItems(where: { location: MENU_1 }) {
    nodes {
      id
      parentId
      label
      url
    }
  }
}
```

The response will be a flat list, even if the menu items have parent/child relationships:

```json
{
  "data": {
    "menuItems": {
      "nodes": [
        {
          "id": "1",
          "parentId": null,
          "label": "Home",
          "url": "/"
        },
        {
          "id": "2",
          "parentId": "1",
          "label": "Child Page",
          "url": "/child"
        },
        {
          "id": "3",
          "parentId": "2",
          "label": "Grandchild",
          "url": "/child/grandchild"
        }
      ]
    }
  }
}
```

## Converting Flat Lists to Hierarchical

### Using JavaScript

Here's a utility function to convert flat data into a hierarchical structure:

```javascript
const flatListToHierarchical = (
    data = [],
    {idKey='id',parentKey='parentId',childrenKey='children'} = {}
) => {
    const tree = [];
    const childrenOf = {};
    
    data.forEach((item) => {
        const newItem = {...item};
        const { [idKey]: id, [parentKey]: parentId = 0 } = newItem;
        childrenOf[id] = childrenOf[id] || [];
        newItem[childrenKey] = childrenOf[id];
        parentId
            ? (
                childrenOf[parentId] = childrenOf[parentId] || []
            ).push(newItem)
            : tree.push(newItem);
    });
    
    return tree;
};
```

Usage example:

```javascript
const flatData = response.data.menuItems.nodes;
const hierarchicalData = flatListToHierarchical(flatData);

// Before (flat data from GraphQL):
console.log(flatData);
```json
[
  {
    "id": "1",
    "parentId": null,
    "label": "Products",
    "url": "/products"
  },
  {
    "id": "2",
    "parentId": "1",
    "label": "Enterprise",
    "url": "/products/enterprise"
  },
  {
    "id": "3",
    "parentId": "1",
    "label": "Small Business",
    "url": "/products/small-business"
  },
  {
    "id": "4",
    "parentId": "2",
    "label": "Enterprise Features",
    "url": "/products/enterprise/features"
  },
  {
    "id": "5",
    "parentId": null,
    "label": "About",
    "url": "/about"
  }
]

// After (hierarchical structure):
console.log(hierarchicalData);
```json
[
  {
    "id": "1",
    "parentId": null,
    "label": "Products",
    "url": "/products",
    "children": [
      {
        "id": "2",
        "parentId": "1",
        "label": "Enterprise",
        "url": "/products/enterprise",
        "children": [
          {
            "id": "4",
            "parentId": "2",
            "label": "Enterprise Features",
            "url": "/products/enterprise/features",
            "children": []
          }
        ]
      },
      {
        "id": "3",
        "parentId": "1",
        "label": "Small Business",
        "url": "/products/small-business",
        "children": []
      }
    ]
  },
  {
    "id": "5",
    "parentId": null,
    "label": "About",
    "url": "/about",
    "children": []
  }
]
```

This transformation makes it easier to:
- Render nested menus and hierarchical layouts
- Traverse parent/child relationships
- Maintain the natural structure of the data

### Using PHP

If you're working with hierarchical data in PHP, here's a similar utility function:

```php
function flat_list_to_hierarchical(
    array $data,
    $id_key = 'id',
    $parent_key = 'parentId',
    $children_key = 'children'
) {
    $tree = [];
    $children_of = [];
    
    foreach ($data as $item) {
        $item = (array) $item;
        $id = $item[$id_key];
        $parent_id = $item[$parent_key] ?? 0;
        
        if (!isset($children_of[$id])) {
            $children_of[$id] = [];
        }
        
        $item[$children_key] = &$children_of[$id];
        
        if ($parent_id) {
            if (!isset($children_of[$parent_id])) {
                $children_of[$parent_id] = [];
            }
            $children_of[$parent_id][] = $item;
        } else {
            $tree[] = $item;
        }
    }
    
    return $tree;
}
```

## Common Use Cases

### Hierarchical Post Types

When querying hierarchical post types like Pages:

```graphql
query Pages {
  pages(first: 100) {
    nodes {
      id
      title
      parentId
      uri
    }
  }
}
```

### Hierarchical Taxonomies

Categories and other hierarchical taxonomies:

```graphql
query Categories {
  categories(first: 100) {
    nodes {
      id
      name
      parentId
      uri
      slug
    }
  }
}
```

### Navigation Menus

Menu items with nested relationships:

```graphql
query MenuItems {
  menuItems(where: { location: MENU_1 }, first: 100) {
    nodes {
      id
      parentId
      label
      url
      # Available menu locations can be configured in WordPress
    }
  }
}
```

### Comments

Comments and their replies:

```graphql
query Comments {
  comments(first: 100) {
    nodes {
      id
      parentDatabaseId
      content
      author {
        node {
          name
        }
      }
      date
    }
  }
}
```

## Best Practices

> [!TIP]
> - Consider where data transformation should occur (client vs server)
> - Cache transformed hierarchical data when possible
> - Be mindful of deep nesting levels
> - Handle empty or malformed data gracefully

### Performance Considerations

> [!IMPORTANT]
> Converting flat lists to hierarchical structures can be computationally expensive with large datasets. Consider:
> - Implementing pagination
> - Caching transformed data
> - Processing data in chunks
> - Using memoization for repeated transformations

## Further Reading

- [Menus Guide](/docs/menus/)
- [Custom Post Types](/docs/custom-post-types/)
- [Custom Taxonomies](/docs/custom-taxonomies/)
- [Performance Guide](/docs/performance/)

> [!NOTE]
> The queries above include the `first` argument for pagination. In production, you should implement proper pagination using `first` and `after` arguments to handle large datasets efficiently.
