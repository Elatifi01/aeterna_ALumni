import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { PageHeader } from '@/components/page-header';
import { index as messagesIndex } from '@/routes/portal/messages';

type Props = {
    message: {
        id: number;
        subject: string;
        content: string;
        sent_at: string | null;
    };
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        weekday: 'long',
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function PortalMessageShow({ message }: Props) {
    return (
        <>
            <Head title={message.subject} />
            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title={message.subject}
                    description={`Recu le ${formatDate(message.sent_at)}`}
                />
                <article className="border-border bg-surface max-w-[720px] rounded-lg border p-6">
                    <div className="text-text text-sm leading-relaxed whitespace-pre-line">
                        {message.content}
                    </div>
                </article>
                <div>
                    <Link
                        href={messagesIndex()}
                        className="text-text-muted hover:text-text inline-flex items-center gap-1.5 text-sm"
                    >
                        <ArrowLeft className="size-4" />
                        Retour aux messages
                    </Link>
                </div>
            </div>
        </>
    );
}

PortalMessageShow.layout = {
    breadcrumbs: [{ title: 'Messages', href: messagesIndex() }],
};
