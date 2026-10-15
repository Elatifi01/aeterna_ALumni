import { usePage } from '@inertiajs/react';
import { useState } from 'react';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PaymentModal } from '@/components/payment-modal';
import type { AppLayoutProps, SharedData } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const [isPaymentModalOpen, setIsPaymentModalOpen] = useState(false);
    const isSuperAdmin =
        usePage<SharedData>().props.auth?.isSuperAdmin ?? false;

    // Payments belong to an organization's subscription; the platform space has none.
    const openPayment = isSuperAdmin
        ? undefined
        : () => setIsPaymentModalOpen(true);

    return (
        <div className="flex min-h-screen flex-col gap-5 bg-[#eaecf2] p-3 font-sans text-[#202224] antialiased sm:p-5 lg:flex-row lg:gap-6 lg:p-6">
            {/* Left Fenco Dock */}
            <AppSidebar onOpenPayment={openPayment} />

            {/* Main Window Container */}
            <div className="flex min-h-[calc(100vh-3rem)] min-w-0 flex-1 flex-col rounded-[36px] border border-white/80 bg-[#f4f5f9] p-4 shadow-2xl sm:p-6 lg:p-7 dark:border-white/10 dark:bg-slate-900/90">
                <AppSidebarHeader
                    breadcrumbs={breadcrumbs}
                    onOpenPayment={openPayment}
                />
                <main className="mt-5 min-w-0 flex-1">{children}</main>
            </div>

            {/* Global Interactive Payment Modal */}
            {!isSuperAdmin && (
                <PaymentModal
                    isOpen={isPaymentModalOpen}
                    onClose={() => setIsPaymentModalOpen(false)}
                />
            )}
        </div>
    );
}
