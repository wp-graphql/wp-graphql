import {
  render,
  screen,
  act,
  fireEvent,
} from "@testing-library/react";
import GraphiQLToolbar from "./GraphiQLToolbar";

describe("GraphiQL Toolbar", () => {
  test("it should render", () => {
    const { container } = render(<GraphiQLToolbar />);
    expect(container).toBeInTheDocument();
  });

  test("it should render with prettify button", () => {
    const { getByText } = render(<GraphiQLToolbar />);
    expect(getByText("Prettify")).toBeTruthy();
  });

  test("it should render with history button", () => {
    const { getByText } = render(<GraphiQLToolbar />);
    expect(getByText("History")).toBeTruthy();
  });
});

describe("Filter before the GraphiQL Toolbar Buttons", () => {
  beforeEach(() => {
    const { hooks } = wpGraphiQL;
    hooks.addFilter(
      "graphiql_toolbar_before_buttons",
      "test-filter",
      (before, props) => {
        return [
          ...before,
          <button key="test-button" data-testid="test-button">
            Test Button
          </button>,
        ];
      }
    );
  });

  afterEach(() => {
    const { hooks } = wpGraphiQL;
    hooks.removeAllFilters("graphiql_toolbar_before_buttons");
  });

  // @todo: figure out how to test that this filter comes _before_ the buttons, as right now it tests the filter renders something, but not that it renders _before_ the buttons
  test("button should render before the other buttons", () => {
    // await act(async () => {
    //   const { container, getByTestId, debug } = render(<GraphiQLToolbar />);
    //   screen.debug();
    //   expect(screen.getByTestId("test-button")).toBeInTheDocument();
    // });

    const { getByTestId } = render(<GraphiQLToolbar />);

    expect(getByTestId("test-button")).toBeInTheDocument();

  });
});

describe("Filter after the GraphiQL Toolbar Buttons", () => {
  beforeEach(() => {
    const { hooks } = wpGraphiQL;
    hooks.addFilter(
      "graphiql_toolbar_after_buttons",
      "test-filter",
      (before, props) => {
        return [
          ...before,
          <button key="test-after-button" data-testid="test-after-button">
            Test Button
          </button>,
        ];
      }
    );
  });

  afterEach(() => {
    const { hooks } = wpGraphiQL;
    hooks.removeAllFilters("graphiql_toolbar_after_buttons");
  });

  // @todo: figure out how to test that this filter comes _after_ the buttons, as right now it tests the filter renders something, but not that it renders _after_ the buttons
  test("button should be after the other buttons", () => {
    const { getByTestId } = render(<GraphiQLToolbar />);

    // expect test button to render after the prettify button
    expect(getByTestId("test-after-button")).toBeInTheDocument();
  });

  test("it should render with the test button", () => {

      const { getByTestId } = render(<GraphiQLToolbar />);
      expect(getByTestId("test-after-button")).toBeInTheDocument();

  });
});

describe("Filter all Toolbar Buttons", () => {
  const clickButton = jest.fn();

  beforeEach(() => {
    const { hooks } = wpGraphiQL;
    hooks.addFilter(
      "graphiql_toolbar_buttons",
      "test-filter",
      (before, props) => {
        return [
          {
            key: "test-button",
            label: "Test Button",
            onClick: () => {
              clickButton();
            },
          },
        ];
      }
    );
  });

  afterEach(() => {
    const { hooks } = wpGraphiQL;
    hooks.removeAllFilters("graphiql_toolbar_buttons");
  });

  test("test button should be the only button", () => {
    render(<GraphiQLToolbar />);

    // expect test button to render after the prettify button
    const allButtons = screen.getAllByRole("button");
    expect(allButtons.length).toBe(1);
    expect(allButtons[0]).toHaveTextContent("Test Button");
  });

  test("clicking button should execute the function", async () => {
    const { getByText } = render(<GraphiQLToolbar />);
    const testButton = getByText("Test Button");
    await act(async () => {
      fireEvent.click(testButton);
    });
    expect(clickButton).toHaveBeenCalled();
  });
});
