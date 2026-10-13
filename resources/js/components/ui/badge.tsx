import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const badgeVariants = cva(
  "inline-flex items-center justify-center rounded-full border px-2.5 py-0.5 text-xs font-semibold w-fit whitespace-nowrap shrink-0 [&>svg]:size-3 gap-1 [&>svg]:pointer-events-none transition-colors",
  {
    variants: {
      variant: {
        default:
          "border-transparent bg-[#1c1e22] text-white",
        secondary:
          "border-transparent bg-[#eef3fd] text-[#5581db] dark:bg-blue-900/30 dark:text-blue-300",
        warning:
          "border-transparent bg-[#fef8e7] text-[#c29631] dark:bg-amber-900/30 dark:text-amber-300",
        success:
          "border-transparent bg-[#eafbf0] text-[#2e9e5b] dark:bg-emerald-900/30 dark:text-emerald-300",
        destructive:
          "border-transparent bg-rose-50 text-rose-600 dark:bg-rose-950/50 dark:text-rose-400",
        outline:
          "border-slate-200 text-slate-700 dark:border-white/10 dark:text-slate-300",
      },
    },
    defaultVariants: {
      variant: "default",
    },
  }
)

function Badge({
  className,
  variant,
  asChild = false,
  ...props
}: React.ComponentProps<"span"> &
  VariantProps<typeof badgeVariants> & { asChild?: boolean }) {
  const Comp = asChild ? Slot : "span"

  return (
    <Comp
      data-slot="badge"
      className={cn(badgeVariants({ variant }), className)}
      {...props}
    />
  )
}

export { Badge, badgeVariants }
