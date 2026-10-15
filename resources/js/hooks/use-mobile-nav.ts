import { useSyncExternalStore } from 'react';

/**
 * Whether the navigation drawer is open on small screens.
 * Shared by the top bar's menu button and the sidebar, which live in different parts of the tree.
 */
let open = false;
const listeners = new Set<() => void>();

function set(value: boolean): void {
    if (open === value) {
        return;
    }

    open = value;
    listeners.forEach((listener) => listener());
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

export const mobileNav = {
    open: () => set(true),
    close: () => set(false),
};

export function useMobileNavOpen(): boolean {
    return useSyncExternalStore(
        subscribe,
        () => open,
        () => false,
    );
}
