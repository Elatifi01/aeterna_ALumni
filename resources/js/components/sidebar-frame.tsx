import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { mobileNav, useMobileNavOpen } from '@/hooks/use-mobile-nav';

/**
 * Where a sidebar lives. From lg up it is the docked capsule beside the page; below that it stays
 * hidden and slides in as a drawer when the top bar's menu button is pressed, closing again as soon
 * as something in it is used, the backdrop is tapped or Escape is pressed.
 */
export function SidebarFrame({ children }: { children: ReactNode }) {
    return (
        <>
            <DockedSidebar>{children}</DockedSidebar>
            <SidebarDrawer>{children}</SidebarDrawer>
        </>
    );
}

/** The desktop dock, with its elastic stretch while the page scrolls. */
function DockedSidebar({ children }: { children: ReactNode }) {
    const [scrollStretch, setScrollStretch] = useState(1);
    const [stretchOrigin, setStretchOrigin] = useState<'top' | 'bottom'>('top');

    useEffect(() => {
        let lastScrollY = window.scrollY;
        let ticking = false;
        let resetTimer: ReturnType<typeof setTimeout> | null = null;

        const handleScroll = () => {
            const delta = window.scrollY - lastScrollY;
            lastScrollY = window.scrollY;

            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(() => {
                const velocity = Math.min(Math.abs(delta), 120);

                if (velocity > 3) {
                    setStretchOrigin(delta > 0 ? 'top' : 'bottom');
                    setScrollStretch(1 + (velocity / 120) * 0.045);

                    if (resetTimer) {
                        clearTimeout(resetTimer);
                    }

                    resetTimer = setTimeout(() => setScrollStretch(1), 180);
                }

                ticking = false;
            });
        };

        window.addEventListener('scroll', handleScroll, { passive: true });

        return () => {
            window.removeEventListener('scroll', handleScroll);

            if (resetTimer) {
                clearTimeout(resetTimer);
            }
        };
    }, []);

    return (
        <aside
            style={{
                transform: `scaleY(${scrollStretch})`,
                transformOrigin:
                    stretchOrigin === 'top' ? 'top center' : 'bottom center',
                transition:
                    scrollStretch === 1
                        ? 'transform 0.45s cubic-bezier(0.34, 1.56, 0.64, 1)'
                        : 'transform 0.08s ease-out',
            }}
            className="sticky top-4 z-40 hidden h-[calc(100vh-2rem)] w-[245px] shrink-0 flex-col justify-between gap-3 self-start font-sans select-none lg:flex"
        >
            {children}
        </aside>
    );
}

/** The small-screen drawer. Always mounted so it can slide out as smoothly as it slides in. */
function SidebarDrawer({ children }: { children: ReactNode }) {
    const open = useMobileNavOpen();
    const { url } = usePage();
    const closeButton = useRef<HTMLButtonElement>(null);

    // A page change always closes it, whatever triggered the navigation.
    useEffect(() => {
        mobileNav.close();
    }, [url]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                mobileNav.close();
            }
        };

        // Growing past the breakpoint shows the dock; the drawer must not stay open behind it.
        const desktop = window.matchMedia('(min-width: 1024px)');
        const onResize = () => desktop.matches && mobileNav.close();

        const previousOverflow = document.documentElement.style.overflow;
        document.documentElement.style.overflow = 'hidden';
        document.addEventListener('keydown', onKey);
        desktop.addEventListener('change', onResize);
        closeButton.current?.focus({ preventScroll: true });

        return () => {
            document.documentElement.style.overflow = previousOverflow;
            document.removeEventListener('keydown', onKey);
            desktop.removeEventListener('change', onResize);
        };
    }, [open]);

    return (
        <div className="lg:hidden">
            <div
                aria-hidden
                onClick={mobileNav.close}
                className={`fixed inset-0 z-50 bg-[#0a0c10]/45 backdrop-blur-[2px] transition-opacity duration-300 ease-out ${
                    open ? 'opacity-100' : 'pointer-events-none opacity-0'
                }`}
            />
            <div
                role="dialog"
                aria-modal="true"
                aria-label="Navigation"
                inert={!open}
                // Using anything in the drawer (a link, logout, payments…) closes it.
                onClickCapture={(event) => {
                    if ((event.target as HTMLElement).closest('a, button')) {
                        mobileNav.close();
                    }
                }}
                className={`fixed inset-y-3 left-3 z-50 flex w-[min(280px,calc(100vw-5rem))] flex-col justify-between gap-3 font-sans transition-transform duration-300 ease-[cubic-bezier(0.32,0.72,0,1)] select-none ${
                    open
                        ? 'translate-x-0'
                        : '-translate-x-[calc(100%+1rem)]'
                }`}
            >
                {children}
                <button
                    ref={closeButton}
                    type="button"
                    aria-label="Fermer le menu"
                    className={`absolute top-3 -right-13 flex size-10 cursor-pointer items-center justify-center rounded-full bg-white text-[#0a0c10] shadow-lg transition-[opacity,transform] duration-200 ease-out active:scale-[0.97] ${
                        open ? 'opacity-100' : 'opacity-0'
                    }`}
                >
                    <X className="size-5" />
                </button>
            </div>
        </div>
    );
}
