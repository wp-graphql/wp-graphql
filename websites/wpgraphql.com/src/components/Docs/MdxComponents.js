import Link from "next/link"
import { cn } from "@/lib/utils"

function Heading({ as, id, children, ...rest }) {
  const Tag = as ?? "h2"
  return (
    <Tag {...rest} id={id}>
      {children}
    </Tag>
  )
}

function LinkedHeading({ id, as, children, className }) {
  if (id) {
    return (
      <Heading
        as={as}
        id={id}
        className={cn(
          "group flex whitespace-pre-wrap pr-4",
          as === "h2" &&
            "mb-2 text-2xl leading-tight font-semibold tracking-tight text-foreground",
          className
        )}
      >
        <span>{children}</span>
        <Link href={`#${id}`} legacyBehavior>
          <a
            className="ml-3 flex items-center text-muted-foreground opacity-0 transition-opacity group-hover:opacity-100"
            aria-label="Anchor"
            href={`#${id}`}
          >
            <span className="flex h-6 w-6 items-center justify-center rounded-md border border-border bg-muted text-primary">
              <svg width="12" height="12" fill="none" aria-hidden="true">
                <path
                  d="M3.75 1v10M8.25 1v10M1 3.75h10M1 8.25h10"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                />
              </svg>
            </span>
          </a>
        </Link>
      </Heading>
    )
  }
  return <h1 />
}

function CustomLink(props) {
  const isAbsolute = /^(?:[a-z]+:)?\/\//i

  if (isAbsolute.test(props.href)) {
    return <a {...props} />
  }

  return <Link {...props} />
}

const components = {
  h2: (props) => <LinkedHeading as="h2" {...props} />,
  h3: (props) => <LinkedHeading as="h3" {...props} />,
  h4: (props) => <LinkedHeading as="h4" {...props} />,
  h5: (props) => <LinkedHeading as="h5" {...props} />,
  h6: (props) => <LinkedHeading as="h6" {...props} />,
  a: CustomLink,
  _Heading: (props) => <Heading {...props} />,
}

export default components
