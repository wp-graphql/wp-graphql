const { print, parse } = wpGraphiQL.GraphQL;

export const isValidQuery = (query) => {
  try {
    const formattedQuery = print(parse(query));
    return formattedQuery;
  } catch (e) {
    console.warn(
      `# Error parsing query from url query param \n\n "${query}"\n\n` +
        e.message
    );
    return false;
  }
};
