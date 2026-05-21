import { Popover } from "@headlessui/react"
import {
  Bars3BottomRightIcon,
  XMarkIcon,
} from "@heroicons/react/24/outline/esm"

export default function DocsSidebar({ children }) {
  return (
    <Popover>
      {({ open }) => (
        <>
          <Popover.Button className="fixed right-6 bottom-6 z-50 inline-flex h-12 w-12 items-center justify-center rounded-full border border-border bg-card text-foreground shadow-elev-md transition-colors hover:bg-accent focus:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            {!open ? (
              <>
                <span className="sr-only">Open Docs Nav menu</span>
                <Bars3BottomRightIcon title="Open" className="h-6 w-6" />
              </>
            ) : (
              <>
                <span className="sr-only">Close Docs Nav menu</span>
                <XMarkIcon title="Close" className="h-6 w-6" />
              </>
            )}
          </Popover.Button>
          <Popover.Panel>
            <div className="fixed inset-0 z-40 h-[100dvh] w-[100dvw] overflow-scroll bg-background p-6 pt-24">
              {children}
            </div>
          </Popover.Panel>
        </>
      )}
    </Popover>
  )
}
