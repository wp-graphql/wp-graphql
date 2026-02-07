import category from "./category"
import author from "./author"
import archive from "./archive"
import index from "./main"
import singleCodeSnippet from "./single-code-snippet"
import singular from "./singular"
import ArchivePost from "./archive-post"
import FrontPage from "./front-page"
import SingleDeveloperReference from "./single-developer-reference";

const templates = {
  category,
  author,
  archive,
  "archive-post": ArchivePost,
  "single-code-snippets": singleCodeSnippet,
  "single-functions": SingleDeveloperReference,
  "single-actions": SingleDeveloperReference,
  "single-filters": SingleDeveloperReference,
  singular,
  "front-page": FrontPage,
  "home": ArchivePost,
  index,
}

export default templates
