import { Head } from '@inertiajs/react';
import AssistantController from '@/actions/App/Http/Controllers/AssistantController';
import {
    AssistantChat,
    type AssistantConversation,
    type AssistantMessage,
} from '@/components/assistant-chat';
import { index as assistantIndex, show } from '@/routes/assistant';

type Props = {
    conversations: AssistantConversation[];
    conversation: { id: number; title: string } | null;
    messages: AssistantMessage[];
    suggestions: string[];
};

export default function StaffAssistant(props: Props) {
    return (
        <>
            <Head title="Aeterna Assistant" />
            <AssistantChat
                {...props}
                description="Interrogez vos donnees alumni en langage naturel"
                storeUrl={AssistantController.store.url()}
                showUrl={(id) => show(id).url}
                destroyForm={(id) => AssistantController.destroy.form(id)}
                newConversationUrl={assistantIndex({ query: { new: 1 } }).url}
            />
        </>
    );
}

StaffAssistant.layout = {
    breadcrumbs: [{ title: 'Aeterna Assistant', href: assistantIndex() }],
};
