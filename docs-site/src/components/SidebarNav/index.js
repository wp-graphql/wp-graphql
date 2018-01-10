import React, { Component } from 'react'
import Link from 'gatsby-link'
import { Affix, Layout, Menu, Icon } from 'antd'
const { SubMenu } = Menu;
const { Sider } = Layout;

const MenuItems = ({pathContext}) => {
  
  let nav = pathContext.nav;
  let menuItems = Object.keys( nav );

  /**
   * Loop through the menuItems to generate the menu items
   * @type {Array}
   */
  const items = menuItems && menuItems.map( (menuItem) => {

    /**
     * If the type is a page, create a menuItem
     */
    if ( nav[menuItem] && nav[menuItem].type === 'page' ) {
      return (
        <Menu.Item key={nav[menuItem].path}>
          <Link to={nav[menuItem].path}>{nav[menuItem].title}</Link>
        </Menu.Item>
      );

    /**
     * If the type is a directory, create a SubMenu with nested menu items
     */
    } else if ( nav[menuItem] && nav[menuItem].type === 'directory' ) {
      let subMenuItems = Object.keys( nav[menuItem].children );
      let subItems = subMenuItems.map( (subMenuItem) => {
        return (
          <Menu.Item key={nav[menuItem].children[subMenuItem].path}>
            <Link to={nav[menuItem].children[subMenuItem].path}>{nav[menuItem].children[subMenuItem].title}</Link>
          </Menu.Item>
        );
      });

      /**
       * Return the SubMenu
       */
      return (
        <SubMenu
          key={nav[menuItem].name}
          title={
            <span>
              <Icon type="file" />
              <span>{nav[menuItem].title}</span>
            </span>
          }
        >{subItems}</SubMenu>
      );
    }
  } );

  /**
   * Split the path so we can determine the openKey for the menu
   */
  let splitPath = pathContext.path.split('/');

  /**
   * Determine the open key for the menu.
   *
   * Ex: if the path is "/some-directory/some-page", then we want the "some-directory" submenu item to be flagged
   * as an open key. 
   * 
   * So, this gets "some-directory" out of the path, and passes it to the menu's "defaultOpenKeys" prop
   * 
   * @type {null}
   */
  let openKeys = splitPath[ splitPath.length - 2 ] ? splitPath[ splitPath.length - 2 ] : null;

  /**
   * Return the Menu
   */
  return <Menu
    mode="inline"
    theme="light"
    style={{height: '100%', borderRight: 0}}
    defaultSelectedKeys={[pathContext.path]}
    defaultOpenKeys={[openKeys]}
  >{items}</Menu>;
};

class SidebarNav extends React.Component {
  render() {
    const pathContext = this.props.pathContext;
    return (
      <Sider style={{
        background: 'white',
        overflow: 'auto',
        minHeight: 'calc(100vh - 64px)' // Viewport height minus header
      }} width={300}>
        <Affix>
          <MenuItems pathContext={pathContext} />
        </Affix>
      </Sider>
    )
  }
}

export default SidebarNav