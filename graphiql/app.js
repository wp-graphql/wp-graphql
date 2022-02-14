import { render } from "@wordpress/element";
import "./app.scss";
import AppWithContext from "./components/App/App.js";

/**
 * Render the application to the DOM
 */
window.addEventListener(
  "load",
  function () {
    render(<AppWithContext />, document.getElementById(`graphiql`));
  },
  false
);
