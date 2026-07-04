import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteAgentModal from '@/components/delete-agent-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index, show } from '@/routes/agents';
import type { Agent, AgentPermissions } from '@/types';

type Props = {
    agents: Agent[];
    permissions: AgentPermissions;
};

export default function AgentsIndex({ agents, permissions }: Props) {
    const currentTeam = usePage().props.currentTeam;
    const [agentToDelete, setAgentToDelete] = useState<Agent | null>(null);

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Agents" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Agents"
                        description="Create and manage the agents that power your support widgets."
                    />

                    {permissions.canManageAgents ? (
                        <Button asChild data-test="agents-new-button">
                            <Link href={create(currentTeam.slug)}>
                                <Plus /> New agent
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="space-y-3">
                    {agents.map((agent) => (
                        <div
                            key={agent.id}
                            data-test="agent-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <Link
                                href={show({
                                    current_team: currentTeam.slug,
                                    agent: agent.id,
                                })}
                                className="min-w-0 flex-1"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">
                                        {agent.name}
                                    </span>
                                    <Badge
                                        variant={
                                            agent.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {agent.status_label}
                                    </Badge>
                                </div>
                                <span className="text-sm text-muted-foreground">
                                    {agent.documents_count ?? 0} document
                                    {agent.documents_count === 1 ? '' : 's'}
                                </span>
                            </Link>

                            {permissions.canManageAgents ? (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        asChild
                                        data-test="agent-edit-button"
                                    >
                                        <Link
                                            href={edit({
                                                current_team: currentTeam.slug,
                                                agent: agent.id,
                                            })}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="agent-delete-button"
                                        onClick={() => setAgentToDelete(agent)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    ))}

                    {agents.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            No agents yet.
                            {permissions.canManageAgents
                                ? ' Create your first agent to get started.'
                                : ''}
                        </p>
                    ) : null}
                </div>
            </div>

            {agentToDelete ? (
                <DeleteAgentModal
                    agent={agentToDelete}
                    open={agentToDelete !== null}
                    onOpenChange={(open) => !open && setAgentToDelete(null)}
                />
            ) : null}
        </>
    );
}

AgentsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Agents',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
