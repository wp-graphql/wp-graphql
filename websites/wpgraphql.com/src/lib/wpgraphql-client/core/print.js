import { print } from "graphql"

export function printQuery(documentOrString) {
  if (typeof documentOrString === "string") return documentOrString
  if (documentOrString && documentOrString.kind === "Document") {
    return print(documentOrString)
  }
  if (documentOrString && documentOrString.loc && typeof documentOrString.loc.source?.body === "string") {
    return print(documentOrString)
  }
  throw new TypeError("printQuery: expected a GraphQL DocumentNode or string")
}

export function getOperation(document) {
  if (!document || document.kind !== "Document") {
    throw new TypeError("getOperation: expected a parsed DocumentNode")
  }
  for (const def of document.definitions) {
    if (def.kind === "OperationDefinition") return def.operation
  }
  return null
}
