import { Form, Head, usePage } from '@inertiajs/react';
import AgentOriginsField from '@/components/agent-origins-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/agents';

export default function AgentsCreate() {
    const currentTeam = usePage().props.currentTeam;

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="New agent" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New agent"
                    description="Give your agent a name and the websites allowed to embed it."
                />

                <Form
                    {...store.form(currentTeam.slug)}
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
                                    placeholder="Support agent"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <AgentOriginsField errors={errors} />

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    data-test="agent-create-button"
                                    disabled={processing}
                                >
                                    Create agent
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

AgentsCreate.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Agents',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: 'New agent',
            href: props.currentTeam ? create(props.currentTeam.slug) : '/',
        },
    ],
});
