import { LineChartOutlined } from "@ant-design/icons";
import {
  cleanup,
  fireEvent,
  render as rtlRender,
  screen,
  waitFor,
  act,
} from "@testing-library/react";
import Router from "./Router";

import { QueryParamProvider, useQueryParam } from "use-query-params";
import { hooks } from "../..";
import { AppContextProvider } from "../../context/AppContext";

beforeEach(() => {
  cleanup();
});

afterEach(() => {
  cleanup();
});

const render = (component) => {
  return rtlRender(
    <QueryParamProvider>
      <AppContextProvider>{component}</AppContextProvider>
    </QueryParamProvider>
  );
};

describe("Router", () => {
  test("it should render", async () => {
    await act(async () => {
      const { container } = render(<Router />);

      expect( container ).toBeInTheDocument();
    });

  });

  test("router should render GraphiQL by default", async () => {

    await act(async () => {
      const { queryByTestId, debug, container } = await render(<Router />);
      await waitFor(() => queryByTestId("router-screen-graphiql"));
    })

  });

  test("clicking router menu item changes route to associated screen", async () => {

      const { queryByTestId } = render(<Router />);

      // Default router layout is graphiql
      expect(
        queryByTestId("router-screen-graphiql")
      ).toBeInTheDocument();

      // help screen should not be rendered
      expect(
        queryByTestId("router-screen-help")
      ).not.toBeInTheDocument();

      // click menu item to change route
      const button = queryByTestId("router-menu-item-help");
      fireEvent.click(button);

      await waitFor(() => {
        queryByTestId("router-screen-help");
      });

      // graphiql screen should no longer be rendered
      expect(
        queryByTestId("router-screen-graphiql")
      ).not.toBeInTheDocument();

      // click menu item to change route
      const graphiqlButton = queryByTestId("router-menu-item-graphiql");
      fireEvent.click(graphiqlButton);

      await waitFor(() => {
        queryByTestId("router-screen-graphiql");
      });

      // graphiql screen should no longer be rendered
      expect(
        queryByTestId("router-screen-help")
      ).not.toBeInTheDocument();

      // help screen should be rendered
      expect(
        queryByTestId("router-screen-graphiql")
      ).toBeInTheDocument();

  });

});

describe("router filters", () => {
  beforeAll(() => {
    const { hooks } = wpGraphiQL;

    hooks.addFilter("graphiql_router_screens", "router-test", (screens) => {
      screens.push({
        id: "testScreen",
        title: "Test Screen Menu Item",
        icon: <LineChartOutlined />,
        render: () => <h2>Test Screen...</h2>,
      });
      return screens;
    });
  });

  afterAll(() => {
    const { hooks } = wpGraphiQL;
    hooks.removeAllFilters("graphiql_router_screens");
  });

  test("clicking filtered screen menu item should replace screen with filtered screen", async () => {

      const{ getByText, queryByTestId, debug } = render(<Router />);

      // Wait for the state change caused by the click
      await waitFor(() => queryByTestId("router-screen-graphiql"));

      // debug();

      expect(getByText("Test Screen Menu Item")).toBeInTheDocument();

      // GraphiQL is the default screen we should see
      expect(queryByTestId("router-screen-graphiql")).toBeInTheDocument();

      // testScreen should not be present until we navigate to it
      expect(
          queryByTestId("router-screen-testScreen")
      ).not.toBeInTheDocument();

      // Click the testScreen menu button
      const button = queryByTestId("router-menu-item-testScreen");
      fireEvent.click(button);

      // Wait for the state change caused by the click
      await waitFor(() => queryByTestId("router-screen-testScreen"));

      // IDE screen should not be present anymore
      expect(
          queryByTestId("router-screen-graphiql")
      ).not.toBeInTheDocument();

      // testScreen screen should now be present
      expect(
          queryByTestId("router-screen-testScreen")
      ).toBeInTheDocument();

      // click menu item to change route
      const graphiqlButton = queryByTestId("router-menu-item-graphiql");
      fireEvent.click(graphiqlButton);

      await waitFor(() => {
        queryByTestId("router-screen-graphiql");
      });

      // graphiql screen should no longer be rendered
      expect(
          queryByTestId("router-screen-testScreen")
      ).not.toBeInTheDocument();

      // help screen should be rendered
      expect(
          queryByTestId("router-screen-graphiql")
      ).toBeInTheDocument();
  });

  test("screens still show if filter returns null", async () => {

      const { hooks } = wpGraphiQL;

      hooks.addFilter("graphiql_router_screens", "test", (screen) => {
        return null;
      });

      const { queryByTestId } = render(<Router />);

      await waitFor(() => queryByTestId("router-screen-graphiql"));

      // IDE is the default screen we should see
      expect(
        queryByTestId("router-screen-graphiql")
      ).toBeInTheDocument();
  });

  test("changing screens, causes screen query param to change", async () => {
    // check default screen query param is equal to default screen
    // click to change screen
    // assert that query param is now equal to new screen
      
      const { queryByTestId } = render(<Router />);

      // Default router layout is graphiql
      expect(
          queryByTestId("router-screen-graphiql")
      ).toBeInTheDocument();

      // graphiql is the default screen we should see
      const queryParam = window.location.search.split("=")[1];
      expect(queryParam).toBe("graphiql");

      // click menu item to change route
      const button = queryByTestId("router-menu-item-help");

        fireEvent.click(button);

        await waitFor(() => {
          queryByTestId("router-screen-help");
        });

        // graphiql screen should no longer be rendered
        expect(
            queryByTestId("router-screen-graphiql")
        ).not.toBeInTheDocument();

        // testScreen screen should now be present
        expect(queryByTestId("router-screen-help")).toBeInTheDocument();

        // test that query param is now equal to new screen
        const newQueryParam = window.location.search.split("=")[1];
        expect(newQueryParam).toBe("help");


  });
});
