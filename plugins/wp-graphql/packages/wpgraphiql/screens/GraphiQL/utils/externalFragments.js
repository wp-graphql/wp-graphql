const { parse } = window.wpGraphiQL.GraphQL;

/**
 * Convert fragment strings to Fragment Definitions for use
 * in GraphiQL type hinting
 *
 * @returns {[]|*[]}
 */
export const getExternalFragments = () => {
  const externalFragments = wpGraphiQLSettings?.externalFragments ?? null;

  if (!externalFragments) {
    return [];
  }

  const fragmentsAsAst = [];

  // Map over the fragments
  externalFragments.map((fragment) => {
    let parsed;
    let parsedDefinition;

    try {
      parsed = parse(fragment);

      // Get the fragment definition
      parsedDefinition = parsed?.definitions[0] ?? null;
    } catch (e) {
      // the fragment couldn't be parsed into AST
    }

    if (parsedDefinition) {
      fragmentsAsAst.push(parsedDefinition);
    }
  });

  return fragmentsAsAst;
};
