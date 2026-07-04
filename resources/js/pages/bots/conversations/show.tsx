import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as botsIndex, show as botShow } from '@/routes/bots';
import conversations from '@/routes/bots/conversations';

type TranscriptMessage = {
    id: string;
    role: string;
    role_label: string;
    content: string;
    sources: Array<{ marker: number; chunkId: string; documentId: string }>;
    retrieval_score: number | null;
    flagged: boolean;
    created_at: string | null;
};

type Props = {
    bot: { id: string; name: string };
    conversation: { id: string; session_id: string; created_at: string | null };
    messages: TranscriptMessage[];
};

export default function ConversationShow({
    bot,
    conversation,
    messages,
}: Props) {
    const currentTeam = usePage().props.currentTeam;

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title={`Conversation ${conversation.session_id}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Transcript"
                    description={conversation.session_id}
                />

                <div className="space-y-3">
                    {messages.map((message) => (
                        <div
                            key={message.id}
                            data-test="transcript-message"
                            className={
                                message.role === 'assistant'
                                    ? 'rounded-lg border bg-muted/40 p-3'
                                    : 'rounded-lg border p-3'
                            }
                        >
                            <div className="mb-1 flex items-center gap-2">
                                <span className="text-xs font-medium text-muted-foreground uppercase">
                                    {message.role_label}
                                </span>
                                {message.flagged ? (
                                    <Badge variant="destructive">Flagged</Badge>
                                ) : null}
                                {message.retrieval_score !== null ? (
                                    <span className="text-xs text-muted-foreground">
                                        score:{' '}
                                        {message.retrieval_score.toFixed(2)}
                                    </span>
                                ) : null}
                            </div>
                            <p className="text-sm whitespace-pre-wrap">
                                {message.content}
                            </p>
                            {message.sources.length > 0 ? (
                                <div className="mt-2 text-xs text-muted-foreground">
                                    {message.sources.length} source
                                    {message.sources.length === 1 ? '' : 's'}
                                </div>
                            ) : null}
                        </div>
                    ))}
                </div>

                <div>
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={conversations.index({
                                current_team: currentTeam.slug,
                                bot: bot.id,
                            })}
                        >
                            Back to conversations
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

ConversationShow.layout = (props: {
    currentTeam?: { slug: string } | null;
    bot: { id: string; name: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Bots',
            href: props.currentTeam ? botsIndex(props.currentTeam.slug) : '/',
        },
        {
            title: props.bot.name,
            href: props.currentTeam
                ? botShow({
                      current_team: props.currentTeam.slug,
                      bot: props.bot.id,
                  })
                : '/',
        },
        { title: 'Conversations', href: '#' },
    ],
});
