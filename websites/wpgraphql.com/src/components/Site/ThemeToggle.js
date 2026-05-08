import { Fragment } from "react"
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
  const setting = theme ?? "system"
  // While next-themes is hydrating, theme can be undefined; use resolvedTheme
  // (or fall back to dark) so the trigger icon doesn't flash.
  const displayTheme = resolvedTheme ?? "dark"

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
