import React from 'react'
import Link from 'gatsby-link'
import { Button } from 'antd'
import styled from 'styled-components'
import {InstantSearch, Hits, SearchBox, Highlight, Snippet} from 'react-instantsearch/dom'

const HomePageHero = styled.div`
  position: relative;
  box-sizing: border-box;
  width: 100%;
  height: 500px;
  padding-top: 150px;
  padding-bottom: 150px;
  background-color: #2ea3f2;
  display: flex;
  flex-direction:column;
  justify-content:center;
  align-items:center;
`;

function Product({ hit }) {
  return (
    <div>
      <Link to={hit.path}>
        <Highlight attributeName="title" hit={hit} />
      </Link>
      {/*<p>*/}
        {/*<Highlight attributeName="shortExcerpt" hit={hit} />*/}
      {/*</p>*/}
    </div>
  );
}

const IndexPage = () => (
  <HomePageHero>
    <div>
      <h1>Goo</h1>
      <p>Welcome to your new Gatsby site.</p>
      <p>Now go build something great.</p>

      <InstantSearch
        appId="0OQW7P3CWR"
        apiKey="7c453e20d23c2c68916cd301063ff8f7"
        indexName="wpgraphqldocs"
      >
        <SearchBox/>
        <Hits hitComponent={Product} />
      </InstantSearch>

      <Button type="primary">
        <Link to="/about">Go to page 2</Link>
      </Button>
    </div>
  </HomePageHero>
)

export default IndexPage
