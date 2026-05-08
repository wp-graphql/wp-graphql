// Next.js adapter. Wraps the framework-agnostic resolveTemplate() into a
// getStaticProps-compatible function and is the only file in this library
// that knows about Next's GetStaticPropsContext shape.

export { getTemplateStaticProps } from "./get-template-static-props.js"
