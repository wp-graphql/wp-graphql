import { setConfig } from "@faustwp/core"
import templates from "./src/wp-templates"
// next.config.js ensures this file exists before webpack processes this module
// If the file doesn't exist, webpack will fail, but next.config.js creates it at the top level
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
