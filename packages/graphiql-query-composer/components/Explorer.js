import ExplorerWrapper from "./ExplorerWrapper";
import QueryBuilder from "./QueryBuilder";
import ErrorBoundary from "./ErrorBoundary";
import { memoizeParseQuery } from "../utils/utils";
import { Spin } from "antd";
const { useAppContext } = wpGraphiQL;
const { useState, useEffect } = wp.element;

/**
 * Establish some markup to wrap the Explorer with. Sets up some dimension and styling constraints.
 *
 * @param schema
 * @param children
 * @returns {JSX.Element}
 * @constructor
 */
const Wrapper = ({ schema, children }) => {
  if (!schema) {
    return (
      <div
        style={{
          fontFamily: "sans-serif",
          textAlign: `center`,
        }}
        className="error-container"
      >
        <Spin />
      </div>
    );
  }

  return (
    <div
      style={{
        fontSize: 12,
        textOverflow: "ellipsis",
        whiteSpace: "nowrap",
        margin: 0,
        padding: 0,
        fontFamily:
          'Consolas, Inconsolata, "Droid Sans Mono", Monaco, monospace',
        display: "flex",
        flexDirection: "column",
        height: "100%",
      }}
      className="graphiql-explorer-root"
    >
      {children}
    </div>
  );
};

/**
 * This is the main Explorer component that adds the "Query Builder" UI to GraphiQL
 *
 * @returns {JSX.Element}
 * @constructor
 */
const Explorer = (props) => {
  const { query, setQuery } = props;
  const { schema } = useAppContext();

  const [document, setDocument] = useState(null);

  useEffect(() => {
    // When the component mounts, parse the query and keep it in memory
    const parsedQuery = memoizeParseQuery(query);

    // Update the document, if needed
    if (document !== parsedQuery) {
      setDocument(parsedQuery);
    }
  }, [query]);

  return (
    <>
      <ExplorerWrapper>
        <ErrorBoundary>
          <Wrapper schema={schema}>
            <QueryBuilder
              schema={schema}
              query={query}
              onEdit={(query) => {
                // When the Query Builder makes changes
                // to the query, this callback from AppContext
                // is executed
                setQuery(query);
              }}
            />
          </Wrapper>
        </ErrorBoundary>
      </ExplorerWrapper>
    </>
  );
};

export default Explorer;
