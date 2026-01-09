import { createRoot, render } from "@wordpress/element";
import "./app.scss";
import AppWithContext from "./components/App/App.js";

/**
 * Render the application to the DOM
 */
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('graphiql');

    if ( ! container ) {
      return;
  }

  /**
   * createRoot only exists in WordPress 6.2+.
   *
   * Once that version is the minimum required, this check can be removed.
   */
  if( createRoot ) {
    createRoot( container ).render( <AppWithContext /> );
  } else {
    render( <AppWithContext />, container );
  }
});
