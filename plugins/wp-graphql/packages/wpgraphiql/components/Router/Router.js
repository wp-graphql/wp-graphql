import {
  CodeOutlined,
  RightOutlined,
  LeftOutlined,
  QuestionCircleOutlined,
} from "@ant-design/icons";
import { Layout, Menu } from "antd";
import { useState, useEffect } from "@wordpress/element";
import styled from "styled-components";
import { hooks } from "../..";
import Help from "../../screens/Help/Help";
import {
  withQueryParams,
  useQueryParams,
  StringParam,
  withDefault,
} from "use-query-params";
import GraphiQL from "../../screens/GraphiQL/GraphiQL";
import { client } from "../../data/client";
import gql from "graphql-tag";
const { useAppContext, GraphQL } = wpGraphiQL;
const { getIntrospectionQuery, buildClientSchema } = GraphQL;

const { Sider } = Layout;

const StyledRouter = styled.div`
  .graphiql-app-screen-sider .ant-layout-sider-trigger {
    position: relative;
  }
  border: 0;
  width: 100%;
`;

/**
 * Get the screens that should be displayed in the router.
 *
 * @returns
 */
const getScreens = () => {
  const screens = [
    {
      id: "graphiql",
      title: "GraphiQL",
      icon: <CodeOutlined />,
      render: () => {
        return <GraphiQL />;
      },
    },
    {
      id: "help",
      title: "Help",
      icon: <QuestionCircleOutlined />,
      render: () => {
        return <Help />;
      },
    },
  ];

  const filteredScreens = hooks.applyFilters(
      "graphiql_router_screens",
      screens
  );

  // @todo: consider better validation to ensure the screens are valid after being filtered?
  // Ensure the filtered screens are an array with at least one screen, else return the default screens
  return true === Array.isArray(filteredScreens) ? filteredScreens : screens;
};

const RouterSider = (props) => {
  const { setQueryParams, setCurrentScreen, currentScreen, screens } = props;
  const [collapsed, setCollapsed] = useState(true);
  const handleCollapse = () => {
    setCollapsed(!collapsed);
  };

  /**
   * Update the current screen, setting the state and the queryParam
   *
   * @param string screenId
   */
  const updateCurrentScreen = (screen) => {
    setCurrentScreen(screen);
    setQueryParams({ screen });
  };

  const getMenuItems = () => {

    let menuItems = [];

    screens &&
    screens.map((screen) => {
      menuItems.push({
        "data-testid":`router-menu-item-${screen.id}`,
        "id": `router-menu-item-${screen.id}`,
        "key":screen.id,
        "icon":screen.icon,
        "onClick": () => {
          updateCurrentScreen(screen.id);
        },
        "label": screen.title
      })
    })

    return menuItems;
  }

  return (
      <Sider
          id="graphiql-router-sider"
          data-testid="graphiql-router-sider"
          className="graphiql-app-screen-sider"
          collapsible
          defaultCollapsed={collapsed}
          collapsed={collapsed}
          trigger={
            <span data-testid="router-menu-collapse-trigger">
          {collapsed ? <RightOutlined /> : <LeftOutlined />}
        </span>
          }
          onCollapse={() => {
            handleCollapse();
          }}
      >
        <Menu
            theme="dark"
            mode="inline"
            selectedKeys={currentScreen}
            activeKey={currentScreen}
            items={getMenuItems()}
        />
      </Sider>
  );
};

const Router = (props) => {
  const [queryParams, setQueryParams] = useQueryParams({
    screen: StringParam,
  });

  const { endpoint, schema, setSchema, setSchemaLoading, setSchemaError, urlMismatch } = useAppContext();

  const { screen } = queryParams;

  const [screens, setScreens] = useState(getScreens());

  useEffect(() => {
    if (null !== schema) {
      return;
    }

    const remoteQuery = getIntrospectionQuery();

    const querySuccess = (res) => {
      // Check if response contains errors
      if (res?.errors?.length > 0) {
        const errorMessage = res.errors.map((e) => e.message).join("; ");
        console.error(`GraphQL introspection errors: ${errorMessage}`);
        setSchemaError({
          message: errorMessage,
          type: "graphql",
          urlMismatch,
        });
        setSchemaLoading(false);
        return;
      }

      const clientSchema = res?.data ? buildClientSchema(res.data) : null;
      if (clientSchema !== schema) {
        setSchema(clientSchema);
        setSchemaError(null);
      }
      setSchemaLoading(false);
    };

    const queryFailure = (error) => {
      console.error(`Failure running getIntrospectionQuery:`, error);

      // Extract error message
      let errorMessage = "Failed to load schema";
      let errorType = "network";

      if (error?.networkError) {
        // Network errors (CORS, connection refused, etc.)
        if (error.networkError.result?.errors) {
          // Server returned an error response (like 403)
          errorMessage = error.networkError.result.errors
            .map((e) => e.message)
            .join("; ");
          errorType = "auth";
        } else if (error.networkError.message) {
          errorMessage = error.networkError.message;
        }
      } else if (error?.graphQLErrors?.length > 0) {
        errorMessage = error.graphQLErrors.map((e) => e.message).join("; ");
        errorType = "graphql";
      } else if (error?.message) {
        errorMessage = error.message;
      }

      setSchemaError({
        message: errorMessage,
        type: errorType,
        urlMismatch,
      });
      setSchemaLoading(false);
    };

    setSchemaLoading(true);
    setSchemaError(null);
    client(endpoint)
      .query({
        query: gql`
          ${remoteQuery}
        `,
      })
      .then(querySuccess, queryFailure);
  }, [endpoint]);

  const getActiveScreenName = () => {
    // find the matching screen
    const activeScreen =
        screens && screens.find((found) => found.id === screen);
    return activeScreen ? activeScreen.id : "graphiql";
  };

  const [currentScreen, setCurrentScreen] = useState(getActiveScreenName());

  const getCurrentScreen = () => {
    return screens.find((screen) => screen.id === currentScreen) ?? screens[0];
  };

  const renderScreen = (props) => {
    const screen = getCurrentScreen();

    return screen ? (
        <Layout className={"router-screen"} data-testid={`router-screen-${screen.id}`}>
          {screen?.render(props)}
        </Layout>
    ) : null;
  };

  return currentScreen ? (
      <StyledRouter data-testid="graphiql-router">
        <Layout style={{ height: `calc(100vh - 32px)`, width: `100%` }}>
          <RouterSider
              setQueryParams={setQueryParams}
              setCurrentScreen={setCurrentScreen}
              currentScreen={currentScreen}
              screens={screens}
          />
          <Layout className="screen-layout" style={{ background: "#fff" }}>
            {renderScreen(props)}
          </Layout>
        </Layout>
      </StyledRouter>
  ) : null;
};

export default withQueryParams(
    {
      screen: withDefault(StringParam, "graphiql"),
    },
    Router
);
