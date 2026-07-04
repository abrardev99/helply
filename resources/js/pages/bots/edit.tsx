import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import BotOriginsField from '@/components/bot-origins-field';
import DeleteBotModal from '@/components/delete-bot-modal';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, show, update } from '@/routes/bots';
import type { Bot, StatusOption } from '@/types';

type Props = {
    bot: Pick<Bot, 'id' | 'name' | 'status' | 'embed_origins'>;
    statusOptions: StatusOption[];
};

export default function BotsEdit({ bot, statusOptions }: Props) {
    const currentTeam = usePage().props.currentTeam;
    const [deleteOpen, setDeleteOpen] = useState(false);

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title={`Edit ${bot.name}`} />

            <div className="flex h-full flex-1 flex-col gap-10 p-4">
                <div className="space-y-6">
                    <Heading
                        title="Bot settings"
                        description="Update your bot's name, status, and allowed origins."
                    />

                    <Form
                        {...update.form({
                            current_team: currentTeam.slug,
                            bot: bot.id,
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
                                        data-test="bot-name-input"
                                        defaultValue={bot.name}
                                        required
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="status">Status</Label>
                                    <select
                                        id="status"
                                        name="status"
                                        data-test="bot-status-select"
                                        defaultValue={bot.status}
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

                                <BotOriginsField
                                    defaultOrigins={bot.embed_origins}
                                    errors={errors}
                                />

                                <div className="flex items-center gap-4">
                                    <Button
                                        type="submit"
                                        data-test="bot-save-button"
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
                        title="Delete bot"
                        description="Permanently delete this bot and all its content."
                    />
                    <div className="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
                        <div className="space-y-0.5 text-red-600 dark:text-red-100">
                            <p className="font-medium">Warning</p>
                            <p className="text-sm">
                                This deletes the bot's documents, content, and
                                conversations. This cannot be undone.
                            </p>
                        </div>
                        <Button
                            variant="destructive"
                            data-test="bot-delete-button"
                            onClick={() => setDeleteOpen(true)}
                        >
                            Delete bot
                        </Button>
                    </div>
                </div>
            </div>

            <DeleteBotModal
                bot={bot}
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
            />
        </>
    );
}

BotsEdit.layout = (props: {
    currentTeam?: { slug: string } | null;
    bot: Pick<Bot, 'id' | 'name'>;
}) => ({
    breadcrumbs: [
        {
            title: 'Bots',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: props.bot.name,
            href: props.currentTeam
                ? show({
                      current_team: props.currentTeam.slug,
                      bot: props.bot.id,
                  })
                : '/',
        },
        {
            title: 'Edit',
            href: props.currentTeam
                ? edit({
                      current_team: props.currentTeam.slug,
                      bot: props.bot.id,
                  })
                : '/',
        },
    ],
});
