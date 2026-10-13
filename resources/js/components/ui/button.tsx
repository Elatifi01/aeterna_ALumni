import { Slot } from "@radix-ui/react-slot"
import { cva, type VariantProps } from "class-variance-authority"
import * as React from "react"

import { cn } from "@/lib/utils"

const buttonVariants = cva(
  "inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-full text-xs sm:text-sm font-semibold transition-[transform,background-color,border-color,box-shadow,opacity] duration-150 ease-out disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg:not([class*='size-'])]:size-4 [&_svg]:shrink-0 outline-none focus-visible:ring-2 focus-visible:ring-blue-500/50 active:scale-[0.97] will-change-transform",
  {
    variants: {
      variant: {
        default:
          "bg-[#1c1e22] hover:bg-slate-800 text-white shadow-xs hover:shadow-sm active:bg-black",
        gradient:
          "bg-gradient-to-r from-[#638fe3] to-[#7aa3f0] hover:opacity-95 text-white shadow-sm hover:shadow-md",
        destructive:
          "bg-rose-600 hover:bg-rose-500 text-white shadow-xs active:bg-rose-700",
        outline:
          "border border-slate-200/80 dark:border-white/10 bg-white dark:bg-slate-800 shadow-2xs hover:bg-slate-50 dark:hover:bg-slate-700 text-[#1c1e22] dark:text-slate-100",
        secondary:
          "bg-slate-100 dark:bg-slate-800 text-[#1c1e22] dark:text-slate-100 hover:bg-slate-200 dark:hover:bg-slate-700",
        ghost: "hover:bg-slate-100/80 dark:hover:bg-white/5 text-slate-700 dark:text-slate-300 rounded-xl",
        link: "text-blue-600 dark:text-blue-400 underline-offset-4 hover:underline",
      },
      size: {
        default: "h-9.5 px-4.5 py-2 has-[>svg]:px-3.5",
        sm: "h-8 px-3.5 has-[>svg]:px-2.5 text-xs",
        lg: "h-11 px-6 has-[>svg]:px-4 text-base",
        icon: "size-9.5 rounded-full",
      },
    },
    defaultVariants: {
      variant: "default",
      size: "default",
    },
  }
)

function Button({
  className,
  variant,
  size,
  asChild = false,
  ...props
}: React.ComponentProps<"button"> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean
  }) {
  const Comp = asChild ? Slot : "button"

  return (
    <Comp
      data-slot="button"
      className={cn(buttonVariants({ variant, size, className }))}
      {...props}
    />
  )
}

export { Button, buttonVariants }
