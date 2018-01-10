import React from 'react'
import PropTypes from 'prop-types'
import Link from 'gatsby-link'
import Helmet from 'react-helmet'
import styled from 'styled-components'
import logo from '../assets/img/logo-horizontal.png'
import { Layout, Menu, Icon } from 'antd'
import * as algolia from 'algoliasearch'
const { Header } = Layout;
const SubMenu = Menu.SubMenu;

import '../styles.css'

const Logo = styled.div`
  float:left;
  >*>img {
    height 50px;
    width: auto;
  }
`;

const TemplateWrapper = ({ children }) => (
  <Layout>
    <Helmet
      title="WPGraphQL Documentation"
      meta={[
        { name: 'description', content: 'Documentation for the free, open-source WPGraphQL Plugin' },
        { name: 'keywords', content: 'WPGraphQL, WordPress, GraphQL, Documentation, Open Source, Plugin, PHP, Free' },
      ]}
    />
    <Header className="header" >
      <Logo>
        <Link to="/" ><img src={logo} /></Link>
      </Logo>

      <Menu
        theme="dark"
        mode="horizontal"
        style={{ lineHeight: '64px', float:'right' }}
      >
        <SubMenu title={<span><Icon type="file" /> Docs</span>}>
          <Menu.Item key="/about">
            <Link to="/getting-started/about"><Icon type="setting" /> Getting Started</Link>
          </Menu.Item>
          <Menu.Item key="/tutorials">
            <Link to="/tutorials/exploring-graphql"><Icon type="edit" /> Tutorials</Link>
          </Menu.Item>
          <Menu.Item key="/recipes">
            <Link to="/recipes/posts"><Icon type="code-o" /> Recipes</Link>
          </Menu.Item>
          <Menu.Item key="/reference">
            <Link to="/reference/actions-filters"><Icon type="book" /> Reference</Link>
          </Menu.Item>
          <Menu.Item key="/faq">
            <Link to="/faq/extending"><Icon type="question-circle-o" /> FAQ</Link>
          </Menu.Item>
        </SubMenu>
        <Menu.Item key="github">
          <a href="https://github.com/wp-graphql/wp-graphql" target="_blank">Github <Icon type="github" /></a>
        </Menu.Item>
      </Menu>
    </Header>
    {children()}
  </Layout>
)

TemplateWrapper.propTypes = {
  children: PropTypes.func,
}

export default TemplateWrapper
