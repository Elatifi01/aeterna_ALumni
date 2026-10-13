import * as React from "react"

import { cn } from "@/lib/utils"

function Input({ className, type, ...props }: React.ComponentProps<"input">) {
  return (
    <input
      type={type}
      data-slot="input"
      className={cn(
        "border-slate-200/90 dark:border-white/10 file:text-foreground placeholder:text-slate-400 selection:bg-blue-600 selection:text-white flex h-10 w-full min-w-0 rounded-full border bg-white dark:bg-slate-800/80 px-4 py-2 text-sm shadow-2xs transition-all duration-150 outline-none file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50",
        "focus-visible:border-blue-500 focus-visible:ring-3 focus-visible:ring-blue-500/20 focus-visible:bg-white dark:focus-visible:bg-slate-900",
        "aria-invalid:ring-rose-500/20 dark:aria-invalid:ring-rose-500/40 aria-invalid:border-rose-500",
        className
      )}
      {...props}
    />
  )
}

export { Input }
