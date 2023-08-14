import * as GraphQL from "graphql/index.js";
import { createHooks } from "@wordpress/hooks";
import { useAppContext, AppContextProvider } from "./context/AppContext";

export const hooks = createHooks();

window.wpGraphiQL = {
  GraphQL,
  hooks,
  useAppContext,
  AppContextProvider,
};
