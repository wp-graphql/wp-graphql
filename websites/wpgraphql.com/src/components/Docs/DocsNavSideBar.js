import { Popover } from "@headlessui/react"
import {
  Bars3BottomRightIcon,
  XMarkIcon,
} from "@heroicons/react/24/outline/esm"

export default function DocsSidebar({ children, className }) {
  return (
    <Popover>
      {({ open }) => (
        <>
          <Popover.Button className="z-50 rounded-md p-2 inline-flex items-center justify-center text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-sky-500 bg-navy dark:bg-slate-200 fixed right-[4rem] bottom-[4rem]">
            {!open ? (
              <>
                <span className="sr-only">Open Docs Nav menu</span>
                <Bars3BottomRightIcon title="Open" className="h-8 w-8" />
              </>
            ) : (
              <>
                <span className="sr-only">Close Docs Nav menu</span>
                <XMarkIcon title="Close" className="h-8 w-8" />
              </>
            )}
          </Popover.Button>
          <Popover.Panel>
            <div className="fixed top-0 pt-24 p-6 left-0 bg-white dark:bg-slate-700 h-[100dvh] w-[100dvw] overflow-scroll">{children}</div>
          </Popover.Panel>
        </>
      )}
    </Popover>
  )
}
