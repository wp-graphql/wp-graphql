import Link from "next/link"
import clsx from "clsx"

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
        className={clsx("group flex whitespace-pre-wrap pr-4", className, {
          "mb-2 text-xxl leading-6 text-navy font-semibold tracking-normal dark:text-slate-200":
            as === "h2",
        })}
      >
        <span>{children}</span>
        <Link href={`#${id}`}>
          <a
            className="flex items-center ml-4 hover:bg-slate-500 "
            aria-label="Anchor"
            href={`#${id}`}
          >
            &#8203;
            <div className="w-6 h-6 text-slate-500 border-1 ring-slate-900/10 rounded-md shadow-lg flex items-center justify-center hover:ring-slate-900/10 hover:shadow hover:bg-slate-400 hover:text-color-700 dark:bg-slate-700 dark:text-slate-300 dark:shadow-none dark:ring-0">
              <svg width="12" height="12" fill="none" aria-hidden="true">
                <path
                  d="M3.75 1v10M8.25 1v10M1 3.75h10M1 8.25h10"
                  stroke="currentColor"
                  strokeWidth="1.5"
                  strokeLinecap="round"
                />
              </svg>
            </div>
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
