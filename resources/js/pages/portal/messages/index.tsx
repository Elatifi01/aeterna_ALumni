import { Form, Head, Link } from '@inertiajs/react';
import { Mail, MailOpen } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import {
    index as messagesIndex,
    readAll,
    show,
} from '@/routes/portal/messages';
import type { Paginated } from '@/types';

type Message = {
    id: number;
    subject: string;
    excerpt: string;
    sent_at: string | null;
    read: boolean;
};

type Props = {
    messages: Paginated<Message>;
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '';
    }

    return new Date(value).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
}

export default function PortalMessagesIndex({ messages }: Props) {
    const unread = messages.data.filter((message) => !message.read).length;

    return (
        <>
            <Head title="Messages" />

            <div className="flex flex-1 flex-col gap-6 p-4">
                <PageHeader
                    title="Messages"
                    description="Les messages que votre etablissement vous a adresses"
                    action={
                        unread > 0 && (
                            <Form
                                {...readAll.form()}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {processing && <Spinner />}
                                        Tout marquer comme lu
                                    </Button>
                                )}
                            </Form>
                        )
                    }
                />

                <section className="border-border bg-surface rounded-lg border">
                    {messages.data.length === 0 ? (
                        <EmptyState
                            icon={Mail}
                            title="Aucun message"
                            description="Les messages de votre etablissement apparaitront ici."
                        />
                    ) : (
                        <ul className="divide-border divide-y">
                            {messages.data.map((message) => (
                                <li key={message.id}>
                                    <Link
                                        href={show(message.id)}
                                        className="hover:bg-surface-muted flex items-start gap-3 px-5 py-4 transition"
                                    >
                                        <span
                                            className={
                                                message.read
                                                    ? 'text-text-muted mt-0.5'
                                                    : 'text-accent mt-0.5'
                                            }
                                        >
                                            {message.read ? (
                                                <MailOpen className="size-4" />
                                            ) : (
                                                <Mail className="size-4" />
                                            )}
                                        </span>

                                        <span className="grid min-w-0 flex-1 gap-0.5">
                                            <span
                                                className={
                                                    message.read
                                                        ? 'text-text truncate text-sm'
                                                        : 'text-text truncate text-sm font-semibold'
                                                }
                                            >
                                                {message.subject}
                                            </span>
                                            <span className="text-text-muted truncate text-xs">
                                                {message.excerpt}
                                            </span>
                                        </span>

                                        <span className="text-text-muted shrink-0 text-xs whitespace-nowrap">
                                            {formatDate(message.sent_at)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

PortalMessagesIndex.layout = {
    breadcrumbs: [{ title: 'Messages', href: messagesIndex() }],
};
