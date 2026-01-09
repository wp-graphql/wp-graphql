import { useExplorer } from "./ExplorerContext";

const { useEffect } = wp.element;
/**
 * This is the wrapping component around the GraphiQL Explorer / Query Builder
 *
 * This provides the wrapping markup and sets up the initial visible state
 *
 * @param props
 * @returns {JSX.Element|null}
 * @constructor
 */
const ExplorerWrapper = (props) => {
  const { isQueryComposerOpen, toggleExplorer } = useExplorer();

  const { children } = props;
  const width = `400px`;

  return isQueryComposerOpen ? (
    <div
      className="docExplorerWrap doc-explorer-app query-composer-wrap"
      style={{
        height: "100%",
        width: width,
        minWidth: width,
        zIndex: 8,
        display: isQueryComposerOpen ? "flex" : "none",
        flexDirection: "column",
        overflow: "hidden",
      }}
    >
      <div className="doc-explorer">
        <div className="doc-explorer-title-bar">
          <div className="doc-explorer-title">Query Composer</div>
          <div className="doc-explorer-rhs">
            <div
              className="docExplorerHide"
              style={{
                cursor: "pointer",
                fontSize: "18px",
                margin: "-7px -8px -6px 0",
                padding: "18px 16px 15px 12px",
                background: 0,
                border: 0,
                lineHeight: "14px",
              }}
              onClick={toggleExplorer}
            >
              {"\u2715"}
            </div>
          </div>
        </div>
        <div
          className="doc-explorer-contents"
          style={{
            backgroundColor: "#ffffff",
            borderTop: `1px solid #d6d6d6`,
            bottom: 0,
            left: 0,
            overflowY: "hidden",
            padding: `0`,
            right: 0,
            top: `47px`,
            position: "absolute",
          }}
        >
          {children}
        </div>
      </div>
    </div>
  ) : null;
};

export default ExplorerWrapper;
