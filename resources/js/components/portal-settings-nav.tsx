import { Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { password, profile } from '@/routes/portal/settings';

const items = [
    { title: 'Profil', href: profile() },
    { title: 'Securite', href: password() },
];

export function PortalSettingsNav() {
    const { isCurrentUrl } = useCurrentUrl();

    return (
        <nav className="flex gap-1" aria-label="Parametres">
            {items.map((item) => (
                <Button
                    key={toUrl(item.href)}
                    size="sm"
                    variant="ghost"
                    asChild
                    className={cn({
                        'bg-surface-muted': isCurrentUrl(item.href),
                    })}
                >
                    <Link href={item.href} prefetch>
                        {item.title}
                    </Link>
                </Button>
            ))}
        </nav>
    );
}
