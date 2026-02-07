import { socialFooterLinks } from "data/social"

export default function Footer() {
  const year = new Date().getFullYear()
  return (
    <footer className="border-t border-slate-200 dark:border-slate-200/5 py-10">
      <div className="max-w-7xl mx-auto px-10 flex flex-col gap-6 justify-between md:flex-row text-slate-500">
        <div className="flex justify-center space-x-6 md:order-2">
          {socialFooterLinks.map((item) => (
            <a
              key={item.name}
              href={item.href}
              className="text-gray-600 dark:text-gray-300 hover:text-gray-500 dark:hover:text-gray-100"
              target={"_blank"}
              rel="noreferrer"
            >
              <div>
                <span className="sr-only">{item.name}</span>
                <item.icon className="h-6 w-6" aria-hidden="true" />
              </div>
            </a>
          ))}
        </div>
        <div className="mt-8 md:mt-0 md:order-1 prose dark:prose-invert">
          <p className="text-center text-base text-navy dark:text-gray-300">
            &copy; {year} WPGraphQL. All rights reserved.
          </p>
        </div>
      </div>
    </footer>
  )
}
