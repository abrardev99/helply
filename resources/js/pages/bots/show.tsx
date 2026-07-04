import { Head, Link, usePage } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit, index, show } from '@/routes/bots';
import type { Bot, BotDocument, BotPermissions } from '@/types';

type Props = {
    bot: Bot;
    documents: BotDocument[];
    permissions: BotPermissions;
};

export default function BotsShow({ bot, documents, permissions }: Props) {
    const currentTeam = usePage().props.currentTeam;

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title={bot.name} />

            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <Heading title={bot.name} />
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

                    {permissions.canManageBots ? (
                        <Button asChild data-test="bot-edit-button">
                            <Link
                                href={edit({
                                    current_team: currentTeam.slug,
                                    bot: bot.id,
                                })}
                            >
                                <Pencil /> Edit bot
                            </Link>
                        </Button>
                    ) : null}
                </div>

                <div className="space-y-3">
                    <Heading variant="small" title="Allowed origins" />
                    {bot.embed_origins.length > 0 ? (
                        <ul className="space-y-1">
                            {bot.embed_origins.map((origin) => (
                                <li
                                    key={origin}
                                    className="text-sm text-muted-foreground"
                                >
                                    <code>{origin}</code>
                                </li>
                            ))}
                        </ul>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No origins configured yet.
                        </p>
                    )}
                </div>

                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title="Documents"
                        description="Content this bot has ingested."
                    />

                    <div className="space-y-2">
                        {documents.map((document) => (
                            <div
                                key={document.id}
                                data-test="bot-document-row"
                                className="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div className="min-w-0">
                                    <div className="truncate font-medium">
                                        {document.title ??
                                            document.source_url ??
                                            'Untitled'}
                                    </div>
                                    <div className="text-sm text-muted-foreground">
                                        {document.type}
                                    </div>
                                </div>
                                <Badge variant="secondary">
                                    {document.status_label}
                                </Badge>
                            </div>
                        ))}

                        {documents.length === 0 ? (
                            <p className="py-8 text-center text-muted-foreground">
                                No documents yet.
                            </p>
                        ) : null}
                    </div>
                </div>
            </div>
        </>
    );
}

BotsShow.layout = (props: {
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
    ],
});
