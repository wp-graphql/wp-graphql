import { setConfig } from "@faustwp/core"
import templates from "./src/wp-templates"
import { readFileSync } from "fs"
import { join, dirname } from "path"
import { fileURLToPath } from "url"

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

let possibleTypes = {}
try {
  const possibleTypesPath = join(__dirname, "possibleTypes.json")
  const possibleTypesContent = readFileSync(possibleTypesPath, "utf-8")
  possibleTypes = JSON.parse(possibleTypesContent)
} catch (error) {
  // File doesn't exist yet, will be generated during build
  // This is expected during Vercel builds where prebuild runs before config is loaded
  console.warn("possibleTypes.json not found, will be generated during build")
}

/**
 * @type {import('@faustwp/core').FaustConfig}
 **/
export default setConfig({
  templates,
  usePersistedQueries: true,
  experimentalToolbar: false,
  possibleTypes,
})
