import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AgentOriginsField from '@/components/agent-origins-field';
import DeleteAgentModal from '@/components/delete-agent-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, show, update } from '@/routes/agents';
import type { Agent, StatusOption } from '@/types';

type Props = {
    agent: Pick<
        Agent,
        | 'id'
        | 'name'
        | 'status'
        | 'embed_origins'
        | 'embedding_model'
        | 'chat_model'
        | 'system_prompt'
        | 'confidence_threshold'
    >;
    statusOptions: StatusOption[];
};

export default function AgentsEdit({ agent, statusOptions }: Props) {
    const currentTeam = usePage().props.currentTeam;
    const [deleteOpen, setDeleteOpen] = useState(false);

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title={`Edit ${agent.name}`} />

            <div className="flex h-full flex-1 flex-col gap-10 p-4">
                <div className="space-y-6">
                    <Heading
                        title="Agent settings"
                        description="Update your agent's name, status, and allowed origins."
                    />

                    <Form
                        {...update.form({
                            current_team: currentTeam.slug,
                            agent: agent.id,
                        })}
                        transform={(data) => ({
                            ...data,
                            embed_origins: (
                                (data.embed_origins as string[]) ?? []
                            ).filter((origin) => origin.trim() !== ''),
                        })}
                        className="max-w-xl space-y-6"
                    >
                        {({ errors, processing }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="name">Name</Label>
                                    <Input
                                        id="name"
                                        name="name"
                                        data-test="agent-name-input"
                                        defaultValue={agent.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        data-test="agent-status-select"
                                        defaultValue={agent.status}
                                        className="h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        {statusOptions.map((option) => (
                                            <option
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </option>
                                        ))}
                                    </select>
                                    <InputError message={errors.status} />
                                </div>

                                <AgentOriginsField
                                    defaultOrigins={agent.embed_origins}
                                    errors={errors}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="chat_model">
                                        Chat model
                                    </Label>
                                    <Input
                                        id="chat_model"
                                        name="chat_model"
                                        data-test="agent-chat-model-input"
                                        defaultValue={agent.chat_model}
                                        required
                                    />
                                    <InputError message={errors.chat_model} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="embedding_model">
                                        Embedding model
                                    </Label>
                                    <Input
                                        id="embedding_model"
                                        name="embedding_model"
                                        data-test="agent-embedding-model-input"
                                        defaultValue={agent.embedding_model}
                                        required
                                    />
                                    <InputError
                                        message={errors.embedding_model}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="confidence_threshold">
                                        Confidence threshold
                                    </Label>
                                    <Input
                                        id="confidence_threshold"
                                        name="confidence_threshold"
                                        type="number"
                                        step="0.05"
                                        min="0"
                                        max="1"
                                        data-test="agent-confidence-threshold-input"
                                        defaultValue={agent.confidence_threshold}
                                        required
                                    />
                                    <InputError
                                        message={errors.confidence_threshold}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="system_prompt">
                                        System prompt
                                    </Label>
                                    <textarea
                                        id="system_prompt"
                                        name="system_prompt"
                                        rows={4}
                                        data-test="agent-system-prompt-input"
                                        defaultValue={agent.system_prompt ?? ''}
                                        className="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    />
                                    <InputError message={errors.system_prompt} />
                                </div>

                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        data-test="agent-save-button"
                                        disabled={processing}
                                    >
                                        Save
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                <div className="space-y-6">
                    <Heading
                        variant="small"
                        title="Delete agent"
                        description="Permanently delete this agent and all its content."
                    />
                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">Warning</p>
                            <p className="text-sm">
                                This deletes the agent's documents, content, and
                                conversations. This cannot be undone.
                            </p>
                        </div>
                        <Button
                            variant="destructive"
                            data-test="agent-delete-button"
                            onClick={() => setDeleteOpen(true)}
                        >
                            Delete agent
                        </Button>
                    </div>
                </div>
            </div>

            <DeleteAgentModal
                agent={agent}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

AgentsEdit.layout = (props: {
    currentTeam?: { slug: string } | null;
    agent: Pick<Agent, 'id' | 'name'>;
}) => ({
    breadcrumbs: [
        {
            title: 'Agents',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.agent.name,
            href: props.currentTeam
                ? show({
                      current_team: props.currentTeam.slug,
                      agent: props.agent.id,
                  })
                : '/',
        },
        {
            title: 'Edit',
            href: props.currentTeam
                ? edit({
                      current_team: props.currentTeam.slug,
                      agent: props.agent.id,
                  })
                : '/',
        },
    ],
});
