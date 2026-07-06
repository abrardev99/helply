import { Head, Link, usePage } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { index as agentsIndex, show as agentShow } from '@/routes/agents';
import conversations from '@/routes/agents/conversations';

type ConversationRow = {
    id: string;
    session_id: string;
    messages_count: number;
    flagged: boolean;
    updated_at: string | null;
};

type Props = {
    agent: { id: string; name: string };
    conversations: ConversationRow[];
    filters: { flagged: boolean };
    analytics: {
        conversations: number;
        messages: number;
        flagged: number;
        flaggedPercent: number;
    };
};

export default function ConversationsIndex({
    agent,
    conversations: rows,
    filters,
    analytics,
}: Props) {
    const currentTeam = usePage().props.currentTeam;

    if (!currentTeam) {
        return null;
    }

    const stats = [
        { label: 'Conversations', value: analytics.conversations },
        { label: 'Messages', value: analytics.messages },
        { label: 'Flagged', value: analytics.flagged },
        { label: 'Flagged %', value: `${analytics.flaggedPercent}%` },
    ];

    return (
        <>
            <Head title={`${agent.name} — Conversations`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="Conversations"
                    description="Review visitor conversations and the questions your agent couldn't answer."
                />

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {stats.map((stat) => (
                        <div
                            key={stat.label}
                            className="rounded-lg border p-4"
                            data-test="conversation-stat"
                        >
                            <div className="text-2xl font-semibold">
                                {stat.value}
                            </div>
                            <div className="text-sm text-muted-foreground">
                                {stat.label}
                            </div>
                        </div>
                    ))}
                </div>

                <div className="flex gap-2">
                    <Button
                        asChild
                        variant={filters.flagged ? 'outline' : 'default'}
                        size="sm"
                    >
                        <Link
                            href={conversations.index({
                                current_team: currentTeam.slug,
                                agent: agent.id,
                            })}
                        >
                            All
                        </Link>
                    </Button>
                    <Button
                        asChild
                        variant={filters.flagged ? 'default' : 'outline'}
                        size="sm"
                        data-test="filter-flagged"
                    >
                        <Link
                            href={conversations.index(
                                {
                                    current_team: currentTeam.slug,
                                    agent: agent.id,
                                },
                                { query: { flagged: 1 } },
                            )}
                        >
                            Flagged only
                        </Link>
                    </Button>
                </div>

                <div className="space-y-2">
                    {rows.map((row) => (
                        <Link
                            key={row.id}
                            href={conversations.show({
                                current_team: currentTeam.slug,
                                agent: agent.id,
                                conversation: row.id,
                            })}
                            data-test="conversation-row"
                            className="flex items-center justify-between rounded-lg border p-3 hover:bg-muted"
                        >
                            <div className="min-w-0">
                                <div className="truncate font-medium">
                                    {row.session_id}
                                </div>
                                <div className="text-sm text-muted-foreground">
                                    {row.messages_count} messages
                                </div>
                            </div>
                            {row.flagged ? (
                                <Badge variant="destructive">Flagged</Badge>
                            ) : null}
                        </Link>
                    ))}

                    {rows.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            No conversations yet.
                        </p>
                    ) : null}
                </div>

                <div>
                    <Button asChild variant="ghost" size="sm">
                        <Link
                            href={agentShow({
                                current_team: currentTeam.slug,
                                agent: agent.id,
                            })}
                        >
                            Back to agent
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

ConversationsIndex.layout = (props: {
    currentTeam?: { slug: string } | null;
    agent: { id: string; name: string };
}) => ({
    breadcrumbs: [
        {
            title: 'Agents',
            href: props.currentTeam ? agentsIndex(props.currentTeam.slug) : '/',
        },
        {
            title: props.agent.name,
            href: props.currentTeam
                ? agentShow({
                      current_team: props.currentTeam.slug,
                      agent: props.agent.id,
                  })
                : '/',
        },
        { title: 'Conversations', href: '#' },
    ],
});
