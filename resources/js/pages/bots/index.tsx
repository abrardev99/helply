import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DeleteBotModal from '@/components/delete-bot-modal';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { create, edit, index, show } from '@/routes/bots';
import type { Bot, BotPermissions } from '@/types';

type Props = {
    bots: Bot[];
    permissions: BotPermissions;
};

export default function BotsIndex({ bots, permissions }: Props) {
    const currentTeam = usePage().props.currentTeam;
    const [botToDelete, setBotToDelete] = useState<Bot | null>(null);

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="Bots" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <Heading
                        title="Bots"
                        description="Create and manage the bots that power your support widgets."
                    />

                    {permissions.canManageBots ? (
                        <Button asChild data-test="bots-new-button">
                            <Link href={create(currentTeam.slug)}>
                                <Plus /> New bot
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="space-y-3">
                    {bots.map((bot) => (
                        <div
                            key={bot.id}
                            data-test="bot-row"
                            className="flex items-center justify-between gap-4 rounded-lg border p-4"
                        >
                            <Link
                                href={show({
                                    current_team: currentTeam.slug,
                                    bot: bot.id,
                                })}
                                className="min-w-0 flex-1"
                            >
                                <div className="flex items-center gap-2">
                                    <span className="font-medium">
                                        {bot.name}
                                    </span>
                                    <Badge
                                        variant={
                                            bot.status === 'active'
                                                ? 'default'
                                                : 'secondary'
                                        }
                                    >
                                        {bot.status_label}
                                    </Badge>
                                </div>
                                <span className="text-sm text-muted-foreground">
                                    {bot.documents_count ?? 0} document
                                    {bot.documents_count === 1 ? '' : 's'}
                                </span>
                            </Link>

                            {permissions.canManageBots ? (
                                <div className="flex items-center gap-2">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        asChild
                                        data-test="bot-edit-button"
                                    >
                                        <Link
                                            href={edit({
                                                current_team: currentTeam.slug,
                                                bot: bot.id,
                                            })}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        data-test="bot-delete-button"
                                        onClick={() => setBotToDelete(bot)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ) : null}
                        </div>
                    ))}

                    {bots.length === 0 ? (
                        <p className="py-8 text-center text-muted-foreground">
                            No bots yet.
                            {permissions.canManageBots
                                ? ' Create your first bot to get started.'
                                : ''}
                        </p>
                    ) : null}
                </div>
            </div>

            {botToDelete ? (
                <DeleteBotModal
                    bot={botToDelete}
                    open={botToDelete !== null}
                    onOpenChange={(open) => !open && setBotToDelete(null)}
                />
            ) : null}
        </>
    );
}

BotsIndex.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Bots',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
    ],
});
