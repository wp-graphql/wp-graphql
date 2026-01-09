import { createRoot, render } from '@wordpress/element';
import Extensions from './Extensions';
import './index.scss';

document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('wpgraphql-extensions');

  if ( ! container ) {
      return;
  }

  /**
   * createRoot only exists in WordPress 6.2+.
   *
   * Once that version is the minimum required, this check can be removed.
   */
  if( createRoot ) {
    createRoot( container ).render( <Extensions /> );
  } else {
    render( <Extensions />, container );
  }
});
