import { useContext, useState, createContext } from "@wordpress/element";
const { hooks } = wpGraphiQL;

export const AuthSwitchContext = createContext();

export const useAuthSwitchContext = () => {
  return useContext(AuthSwitchContext);
};

export const AuthSwitchProvider = ({ children }) => {
  const getDefaultState = () => {
    const localValue = window?.localStorage.getItem(
      "graphiql:usePublicFetcher"
    );
    return !(localValue && localValue === "false");
  };

  const [usePublicFetcher, setUsePublicFetcher] = useState(getDefaultState());

  const toggleUsePublicFetcher = () => {
    const newState = !usePublicFetcher;
    window.localStorage.setItem(
      "graphiql:usePublicFetcher",
      newState.toString()
    );
    setUsePublicFetcher(newState);
  };

  const value = hooks.applyFilters(
    "graphiql_auth_switch_context_default_value",
    {
      usePublicFetcher,
      setUsePublicFetcher,
      toggleUsePublicFetcher,
    }
  );

  return (
    <AuthSwitchContext.Provider value={value}>
      {children}
    </AuthSwitchContext.Provider>
  );
};
