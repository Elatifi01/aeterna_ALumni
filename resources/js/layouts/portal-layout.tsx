import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { PortalSidebar } from '@/components/portal-sidebar';
import type { BreadcrumbItem } from '@/types';

export default function PortalLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <div className="min-h-screen bg-[#eaecf2] p-3 sm:p-5 lg:p-6 flex flex-col md:flex-row gap-5 lg:gap-6 font-sans antialiased text-[#202224]">
            {/* Left Fenco Dock */}
            <PortalSidebar />

            {/* Main Window */}
            <div className="flex-1 min-w-0 bg-[#f4f5f9] dark:bg-slate-900/90 rounded-[36px] shadow-2xl p-4 sm:p-6 lg:p-7 flex flex-col border border-white/80 dark:border-white/10 min-h-[calc(100vh-3rem)]">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <main className="flex-1 min-w-0 mt-5">
                    {children}
                </main>
            </div>
        </div>
    );
}
