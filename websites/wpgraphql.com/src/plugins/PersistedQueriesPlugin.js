import {createPersistedQueryLink} from '@apollo/client/link/persisted-queries';
import {HttpLink} from "@apollo/client";
import {sha256} from 'crypto-hash';
import {getGraphqlEndpoint} from "@faustwp/core/dist/mjs/lib/getGraphqlEndpoint";

const httpLink = new HttpLink({ uri: getGraphqlEndpoint() });
const persistedQueriesLink = createPersistedQueryLink({ 
  sha256,
  useGETForHashedQueries: true 
});

class PersistedQueriesPlugin {
  apply({ addFilter }) {
    addFilter('apolloClientOptions', 'faust', (apolloClientOptions) => {
      const existingLink = apolloClientOptions?.link;
      return {
        ...apolloClientOptions,
        link: existingLink instanceof HttpLink ? persistedQueriesLink.concat( existingLink ) : persistedQueriesLink.concat(httpLink)
      }
    });
  }
}

export default PersistedQueriesPlugin;
