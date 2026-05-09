import { Fragment, useEffect, useState } from "react"
import { Listbox } from "@headlessui/react"
import { useTheme } from "next-themes"
import { Sun, Moon, Monitor } from "lucide-react"

import { cn } from "@/lib/utils"

const settings = [
  { value: "light", label: "Light", Icon: Sun },
  { value: "dark", label: "Dark", Icon: Moon },
  { value: "system", label: "System", Icon: Monitor },
]

export default function ThemeToggle({ panelClassName = "mt-4" }) {
  const { theme, setTheme, resolvedTheme } = useTheme()
  // Gate on `mounted` to avoid a hydration mismatch: next-themes' inline
  // script applies the stored theme class to <html> before React hydrates,
  // so the client knows the user's preference on the first render but the
  // server doesn't. Render a stable placeholder until mount, then swap to
  // the real value.
  const [mounted, setMounted] = useState(false)
  useEffect(() => setMounted(true), [])

  const setting = theme ?? "system"
  const displayTheme = mounted ? (resolvedTheme ?? "dark") : "dark"

  return (
    <Listbox value={setting} onChange={setTheme}>
      <Listbox.Label className="sr-only">Theme</Listbox.Label>
      <Listbox.Button
        type="button"
        className="rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-accent transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
      >
        {displayTheme === "dark" ? (
          <Moon className="size-5" aria-hidden="true" />
        ) : (
          <Sun className="size-5" aria-hidden="true" />
        )}
      </Listbox.Button>
      <Listbox.Options
        className={cn(
          "absolute z-50 top-full max-md:right-[60px] w-36 py-1 -mt-5",
          "rounded-lg border border-border bg-popover text-popover-foreground shadow-elev-lg",
          "text-sm font-medium overflow-hidden",
          panelClassName
        )}
      >
        {settings.map(({ value, label, Icon }) => (
          <Listbox.Option key={value} value={value} as={Fragment}>
            {({ active, selected }) => (
              <li
                className={cn(
                  "py-1.5 px-3 flex items-center cursor-pointer gap-2",
                  selected && "text-primary",
                  active && "bg-accent"
                )}
              >
                <Icon className="size-4" aria-hidden="true" />
                {label}
              </li>
            )}
          </Listbox.Option>
        ))}
      </Listbox.Options>
    </Listbox>
  )
}
