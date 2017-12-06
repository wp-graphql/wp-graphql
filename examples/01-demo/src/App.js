import React, { Component } from 'react';
import { ApolloClient } from 'apollo-client';
import { ApolloProvider } from 'react-apollo';
import { setContext } from 'apollo-link-context';
import { HttpLink } from 'apollo-link-http';
import { InMemoryCache } from 'apollo-cache-inmemory';
import { graphql } from 'react-apollo';
import gql from 'graphql-tag';
import logo from './logo.svg';
import './App.css';

const httpLink = new HttpLink({
  uri: 'http://denverpost.com/graphql'
});

localStorage.setItem( 'authToken', 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOlwvXC93d3cuZGVudmVycG9zdC5jb20iLCJpYXQiOjE1MTI1OTA3MDksIm5iZiI6MTUxMjU5MDcwOSwiZXhwIjoxNTEzMTk1NTA5LCJkYXRhIjp7InVzZXIiOnsiaWQiOiI2In19fQ.uGmwmEyj1TPPQUm6ytgkdZFxTJNRrliUZl1DshijYNw' );

const authLink = setContext((_, { headers }) => {
  // get the authentication token from local storage if it exists
  const token = localStorage.getItem('authToken');
  // return the headers to the context so httpLink can read them
  return {
    headers: {
      ...headers,
      authorization: token ? `Bearer ${token}` : null,
    }
  }
});

const client = new ApolloClient({
  // By default, this client will send queries to the
  //  `/graphql` endpoint on the same host
  // Pass the configuration option { uri: YOUR_GRAPHQL_API_URL } to the `HttpLink` to connect
  // to a different host
  link: authLink.concat(httpLink),
  cache: new InMemoryCache()
});

function ItemsList({ data: { posts, refetch } }) {
  return (
    <div>
      <button onClick={() => refetch()}>
        Refresh
      </button>
      <ul>
        {posts && posts.edges && posts.edges.map(post => (
          <li key={post.node.id}>
            {post.node.title}
          </li>
        ))}
      </ul>
    </div>
  );
}

const List = graphql(gql`
    query TodoAppQuery {
        posts {
            edges {
                node {
                    id
                    title
                    date
                }    
            }
        }
    }
`)(ItemsList);

class App extends Component {
  render() {
    return (
      <ApolloProvider client={client}>
        <div className="App">
          <header className="App-header">
            <img src={logo} className="App-logo" alt="logo"/>
            <h1 className="App-title">Heroku Rox! Autodeploy</h1>
          </header>
          <List />
        </div>
      </ApolloProvider>
    );
  }
}

export default App;
