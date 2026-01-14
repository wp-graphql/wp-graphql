import { useAuthSwitchContext } from "./AuthSwitchContext";
import { Avatar, Badge, Tooltip } from "antd";
import { UserAddOutlined } from "@ant-design/icons";
import styled from "styled-components";

const StyledAvatar = styled.div`
  .ant-avatar > img {
    filter: grayscale(${(props) => (props?.usePublicFetcher ? 100 : 0)});
  }
`;

/**
 * Provides the AuthSwitch button allowing users to toggle between executing as a public user and the
 * logged-in user.
 *
 * @returns {JSX.Element}
 * @constructor
 */
const AuthSwitch = () => {
  const authSwitch = useAuthSwitchContext();
  const { usePublicFetcher, toggleUsePublicFetcher } = authSwitch;

  const title = usePublicFetcher
    ? "Switch to execute as the logged-in user"
    : "Switch to execute as a public user";

  // the antd-app class is used to take advantage of the styling provided by the antd component library
  return (
    <StyledAvatar
      usePublicFetcher={usePublicFetcher}
      className="antd-app graphiql-auth-toggle"
      data-testid="auth-switch"
    >
      <span style={{ margin: "0 5px" }}>
        <Tooltip
          getPopupContainer={() =>
            window.document.getElementsByClassName(`graphiql-auth-toggle`)[0]
          }
          placement="bottom"
          title={title}
        >
          <button
              aria-label={title}
              type="button"
              onClick={toggleUsePublicFetcher}
              className="toolbar-button"
          >
              <Badge dot={!usePublicFetcher} status="success">
                <Avatar
                  shape={"circle"}
                  size={"small"}
                  title={title}
                  src={window?.wpGraphiQLSettings?.avatarUrl ?? null}
                  icon={
                    window?.wpGraphiQLSettings?.avatarUrl ? null : (
                      <UserAddOutlined />
                    )
                  }
                />
              </Badge>
          </button>
        </Tooltip>
      </span>
    </StyledAvatar>
  );
};

export default AuthSwitch;
