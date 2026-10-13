import { Form, Link, router } from '@inertiajs/react';
import {
    Briefcase,
    CalendarDays,
    Database,
    Lightbulb,
    Mail,
    Plus,
    Send,
    Sparkles,
    Star,
    Trash2,
    UserRound,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';
import type { RouteFormDefinition } from '@/wayfinder';

export type AssistantMessage = {
    id: number;
    role: 'user' | 'assistant';
    content: string | null;
    tools_used: string[];
    created_at: string | null;
};

export type AssistantConversation = {
    id: number;
    title: string;
    updated_at: string | null;
};

type Props = {
    conversations: AssistantConversation[];
    conversation: { id: number; title: string } | null;
    messages: AssistantMessage[];
    suggestions: string[];
    description: string;
    storeUrl: string;
    showUrl: (id: number) => string;
    destroyForm: (id: number) => RouteFormDefinition<'post'>;
    newConversationUrl: string;
};

const toolLabels: Record<string, { label: string; icon: LucideIcon }> = {
    search_jobs: { label: 'Offres emploi.ma', icon: Briefcase },
    get_upcoming_events: { label: 'Evenements', icon: CalendarDays },
    get_my_profile: { label: 'Votre profil', icon: UserRound },
    get_my_applications: { label: 'Vos candidatures', icon: Star },
    search_alumni: { label: 'Alumni', icon: Users },
    get_alumni_statistics: { label: 'Statistiques alumni', icon: Database },
    draft_campaign: { label: 'Brouillon de campagne', icon: Mail },
    list_campaigns: { label: 'Campagnes', icon: Mail },
    update_campaign_draft: { label: 'Campagne modifiee', icon: Mail },
};

export function AssistantChat({
    conversations,
    conversation,
    messages,
    suggestions,
    description,
    storeUrl,
    showUrl,
    destroyForm,
    newConversationUrl,
}: Props) {
    const [message, setMessage] = useState('');
    const [sending, setSending] = useState(false);
    const [pending, setPending] = useState<string | null>(null);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const endRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages.length, pending]);

    useEffect(() => {
        setPending(null);
    }, [messages]);

    const send = (text: string) => {
        if (text.trim() === '' || sending) {
            return;
        }

        setPending(text);
        setMessage('');
        setSending(true);
        setShowSuggestions(false);

        router.post(
            storeUrl,
            { message: text, conversation_id: conversation?.id ?? null },
            {
                preserveScroll: true,
                onFinish: () => setSending(false),
                onError: () => {
                    setPending(null);
                    setMessage((current) => (current === '' ? text : current));
                },
            },
        );
    };

    return (
        <div className="flex flex-1 flex-col gap-6 p-4">
            <PageHeader
                title="Aeterna Assistant"
                description={description}
                action={
                    conversation !== null && (
                        <Button asChild variant="outline">
                            <Link href={newConversationUrl}>
                                <Plus className="size-4" />
                                Nouvelle conversation
                            </Link>
                        </Button>
                    )
                }
            />
            <div className="grid flex-1 gap-5 lg:grid-cols-[240px_1fr]">
                <aside className="hidden lg:block">
                    <ul className="space-y-1">
                        {conversations.map((one) => (
                            <li
                                key={one.id}
                                className="group flex items-center gap-1"
                            >
                                <Link
                                    href={showUrl(one.id)}
                                    className={cn(
                                        'text-text-secondary hover:bg-surface-muted min-w-0 flex-1 truncate rounded-md px-3 py-2 text-sm transition',
                                        conversation?.id === one.id &&
                                            'bg-surface-muted text-text font-medium',
                                    )}
                                >
                                    {one.title}
                                </Link>
                                <Form
                                    {...destroyForm(one.id)}
                                    options={{ preserveScroll: true }}
                                >
                                    <button
                                        type="submit"
                                        aria-label={`Supprimer ${one.title}`}
                                        className="text-text-muted hover:text-danger rounded-md p-2 opacity-0 transition group-hover:opacity-100"
                                    >
                                        <Trash2 className="size-4" />
                                    </button>
                                </Form>
                            </li>
                        ))}
                    </ul>
                </aside>
                <section className="border-border bg-surface flex min-h-[60vh] flex-col rounded-lg border">
                    <div className="flex-1 space-y-4 overflow-y-auto p-5">
                        {messages.length === 0 && pending === null ? (
                            <div className="flex h-full flex-col items-center justify-center gap-5 text-center">
                                <span className="bg-brand-light/30 text-primary dark:text-text flex size-14 items-center justify-center rounded-full">
                                    <Sparkles className="size-7" />
                                </span>
                                <div>
                                    <p className="text-text text-base font-semibold">
                                        Posez votre question
                                    </p>
                                    <p className="text-text-secondary mt-1 text-sm">
                                        Aeterna Assistant repond a partir des
                                        donnees reelles de votre etablissement.
                                    </p>
                                </div>
                                <SuggestionChips
                                    suggestions={suggestions}
                                    disabled={sending}
                                    onPick={send}
                                />
                            </div>
                        ) : (
                            messages.map((one) => (
                                <div
                                    key={one.id}
                                    className={cn(
                                        'flex',
                                        one.role === 'user'
                                            ? 'justify-end'
                                            : 'justify-start',
                                    )}
                                >
                                    <div
                                        className={cn(
                                            'max-w-[80%] rounded-lg px-4 py-3 text-sm whitespace-pre-line',
                                            one.role === 'user'
                                                ? 'bg-primary text-accent-contrast'
                                                : 'bg-surface-muted text-text',
                                        )}
                                    >
                                        {one.content}

                                        {one.tools_used.length > 0 && (
                                            <div className="border-border/60 mt-3 flex flex-wrap items-center gap-2 border-t pt-2">
                                                <span className="text-text-muted flex items-center gap-1 text-xs">
                                                    <Database className="size-3" />
                                                    Donnees Aeterna :
                                                </span>
                                                {one.tools_used.map((tool) => {
                                                    const known =
                                                        toolLabels[tool];
                                                    const Icon =
                                                        known?.icon ?? Database;

                                                    return (
                                                        <span
                                                            key={tool}
                                                            className="bg-surface text-text-secondary flex items-center gap-1 rounded-full px-2 py-0.5 text-xs"
                                                        >
                                                            <Icon className="size-3" />
                                                            {known?.label ??
                                                                tool}
                                                        </span>
                                                    );
                                                })}
                                            </div>
                                        )}
                                    </div>
                                </div>
                            ))
                        )}

                        {pending !== null && (
                            <div className="flex justify-end">
                                <div className="bg-primary text-accent-contrast max-w-[80%] rounded-lg px-4 py-3 text-sm whitespace-pre-line">
                                    {pending}
                                </div>
                            </div>
                        )}

                        {sending && (
                            <div className="flex justify-start">
                                <div className="bg-surface-muted text-text-secondary flex items-center gap-2 rounded-lg px-4 py-3 text-sm">
                                    <Spinner />
                                    Aeterna Assistant reflechit…
                                </div>
                            </div>
                        )}

                        <div ref={endRef} />
                    </div>
                    {messages.length > 0 && showSuggestions && (
                        <div className="border-border border-t px-4 pt-3">
                            <SuggestionChips
                                suggestions={suggestions}
                                disabled={sending}
                                onPick={send}
                            />
                        </div>
                    )}

                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            send(message);
                        }}
                        className="border-border flex items-end gap-2 border-t p-4"
                    >
                        {messages.length > 0 && suggestions.length > 0 && (
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                aria-label="Suggestions de questions"
                                aria-pressed={showSuggestions}
                                title="Suggestions de questions"
                                onClick={() =>
                                    setShowSuggestions((open) => !open)
                                }
                                className="h-12 w-12"
                            >
                                <Lightbulb className="size-4" />
                            </Button>
                        )}

                        <textarea
                            value={message}
                            onChange={(event) => setMessage(event.target.value)}
                            onKeyDown={(event) => {
                                if (event.key === 'Enter' && !event.shiftKey) {
                                    event.preventDefault();
                                    send(message);
                                }
                            }}
                            rows={1}
                            maxLength={2000}
                            placeholder="Posez votre question a Aeterna…"
                            className="border-border-strong bg-surface text-text max-h-32 min-h-12 flex-1 resize-none rounded-md border px-3 py-3 text-sm"
                        />
                        <Button
                            type="submit"
                            disabled={sending || message.trim() === ''}
                            className="h-12"
                        >
                            {sending ? (
                                <Spinner />
                            ) : (
                                <Send className="size-4" />
                            )}
                            Envoyer
                        </Button>
                    </form>
                </section>
            </div>
        </div>
    );
}

function SuggestionChips({
    suggestions,
    disabled,
    onPick,
}: {
    suggestions: string[];
    disabled: boolean;
    onPick: (text: string) => void;
}) {
    if (suggestions.length === 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2">
            {suggestions.map((one) => (
                <button
                    key={one}
                    type="button"
                    onClick={() => onPick(one)}
                    disabled={disabled}
                    className="border-border text-text-secondary hover:bg-surface-muted rounded-full border px-3 py-1.5 text-sm transition disabled:opacity-50"
                >
                    {one}
                </button>
            ))}
        </div>
    );
}
