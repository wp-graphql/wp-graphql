# Connection

This directory stores registrations of connections for the Schema. 

The filename represents the Type the connections are going TO. 

For example, `Comments.php` registers connections from other types TO the Comment type, such as RootQueryToCommentConnection and UserToCommentConnection

Said registered connections enable queries like so: 

### RootQueryToCommentConnection
```
{
  comments { 
    edges { 
      node {
        id
        content
      }
    }
  }
}
```

### UserToCommentConnection
```
{
  users {
    edges {
      node {
        comments {
          edges {
            node {
              id
              content
            }
          }
        }
      }
    }
  }
}
```