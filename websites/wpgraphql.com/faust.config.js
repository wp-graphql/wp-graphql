import { setConfig } from "@faustwp/core"
import templates from "./src/wp-templates"
// Webpack config ensures this file exists (creates empty fallback if missing)
import possibleTypes from "./possibleTypes.json"

/**
 * @type {import('@faustwp/core').FaustConfig}
 **/
export default setConfig({
  templates,
  usePersistedQueries: true,
  experimentalToolbar: false,
  possibleTypes,
})
