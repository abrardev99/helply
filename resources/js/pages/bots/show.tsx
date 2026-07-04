import { Form, Head, Link, usePage } from '@inertiajs/react';
import { MessagesSquare, Pencil, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit, index, recrawl, reembed, show } from '@/routes/bots';
import conversations from '@/routes/bots/conversations';
import documents from '@/routes/bots/documents';
import pdfs from '@/routes/bots/pdfs';
import sources from '@/routes/bots/sources';
import type { Bot, BotDocument, BotPermissions } from '@/types';

type Props = {
    bot: Bot;
    documents: BotDocument[];
    embedding: { total: number; embedded: number };
    widgetScriptUrl: string;
    permissions: BotPermissions;
};

const statusVariant: Record<string, 'default' | 'secondary' | 'destructive'> = {
    done: 'default',
    processing: 'secondary',
    pending: 'secondary',
    failed: 'destructive',
};

export default function BotsShow({
    bot,
    documents: botDocuments,
    embedding,
    widgetScriptUrl,
    permissions,
}: Props) {
    const currentTeam = usePage().props.currentTeam;
    const [copied, setCopied] = useState(false);

    if (!currentTeam) {
        return null;
    }

    const embedSnippet = `<script src="${widgetScriptUrl}" data-bot-id="${bot.id}" defer></script>`;

    const copySnippet = () => {
        navigator.clipboard?.writeText(embedSnippet).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        });
    };

    return (
        <>
            <Head title={bot.name} />

            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <div className="flex items-start justify-between">
                    <div className="flex items-center gap-3">
                        <Heading title={bot.name} />
                        <Badge
                            variant={
                                bot.status === 'active' ? 'default' : 'secondary'
                            }
                        >
                            {bot.status_label}
                        </Badge>
                    </div>

                    <div className="flex gap-2">
                        <Button asChild variant="secondary" data-test="bot-conversations-button">
                            <Link
                                href={conversations.index({
                                    current_team: currentTeam.slug,
                                    bot: bot.id,
                                })}
                            >
                                <MessagesSquare /> Conversations
                            </Link>
                        </Button>

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
                        title="Embed on your site"
                        description="Paste this into your site's <body>. The bot only replies on domains listed in Allowed origins above."
                    />
                    <div className="flex max-w-2xl items-center gap-2">
                        <code
                            data-test="embed-snippet"
                            className="flex-1 overflow-x-auto rounded-md border bg-muted px-3 py-2 text-xs whitespace-nowrap"
                        >
                            {embedSnippet}
                        </code>
                        <Button
                            variant="secondary"
                            size="sm"
                            data-test="copy-embed-button"
                            onClick={copySnippet}
                        >
                            {copied ? 'Copied' : 'Copy'}
                        </Button>
                    </div>
                </div>

                {permissions.canManageBots ? (
                    <div className="space-y-3">
                        <Heading
                            variant="small"
                            title="Add a website"
                            description="We'll crawl the site's sitemap.xml and ingest each page."
                        />

                        <Form
                            {...sources.store.form({
                                current_team: currentTeam.slug,
                                bot: bot.id,
                            })}
                            resetOnSuccess
                            className="flex max-w-xl items-start gap-2"
                        >
                            {({ errors, processing }) => (
                                <div className="flex-1 space-y-2">
                                    <Label htmlFor="url" className="sr-only">
                                        Website URL
                                    </Label>
                                    <div className="flex gap-2">
                                        <Input
                                            id="url"
                                            name="url"
                                            type="url"
                                            data-test="website-url-input"
                                            placeholder="https://docs.example.com"
                                            required
                                        />
                                        <Button
                                            type="submit"
                                            data-test="add-website-button"
                                            disabled={processing}
                                        >
                                            Crawl
                                        </Button>
                                    </div>
                                    <InputError message={errors.url} />
                                </div>
                            )}
                        </Form>
                    </div>
                ) : null}

                {permissions.canManageBots ? (
                    <div className="space-y-3">
                        <Heading
                            variant="small"
                            title="Upload a PDF"
                            description="We'll extract the text and ingest it as content."
                        />

                        <Form
                            {...pdfs.store.form({
                                current_team: currentTeam.slug,
                                bot: bot.id,
                            })}
                            resetOnSuccess
                            className="max-w-xl space-y-2"
                        >
                            {({ errors, processing }) => (
                                <>
                                    <div className="flex gap-2">
                                        <Input
                                            id="file"
                                            name="file"
                                            type="file"
                                            accept="application/pdf"
                                            data-test="pdf-file-input"
                                            required
                                        />
                                        <Button
                                            type="submit"
                                            data-test="upload-pdf-button"
                                            disabled={processing}
                                        >
                                            Upload
                                        </Button>
                                    </div>
                                    <InputError message={errors.file} />
                                </>
                            )}
                        </Form>
                    </div>
                ) : null}

                <div className="flex items-center justify-between rounded-lg border p-4">
                    <div>
                        <p className="font-medium">Embeddings</p>
                        <p className="text-sm text-muted-foreground">
                            {embedding.embedded} / {embedding.total} chunks
                            embedded
                        </p>
                    </div>
                    {permissions.canManageBots && embedding.total > 0 ? (
                        <Form
                            {...reembed.form({
                                current_team: currentTeam.slug,
                                bot: bot.id,
                            })}
                        >
                            {({ processing }) => (
                                <Button
                                    type="submit"
                                    variant="secondary"
                                    size="sm"
                                    data-test="reembed-button"
                                    disabled={processing}
                                >
                                    <RefreshCw /> Re-embed
                                </Button>
                            )}
                        </Form>
                    ) : null}
                </div>

                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <Heading
                            variant="small"
                            title="Documents"
                            description="Content this bot has ingested."
                        />

                        {permissions.canManageBots && botDocuments.length > 0 ? (
                            <Form
                                {...recrawl.form({
                                    current_team: currentTeam.slug,
                                    bot: bot.id,
                                })}
                            >
                                {({ processing }) => (
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        size="sm"
                                        data-test="recrawl-button"
                                        disabled={processing}
                                    >
                                        <RefreshCw /> Re-crawl
                                    </Button>
                                )}
                            </Form>
                        ) : null}
                    </div>

                    <div className="space-y-2">
                        {botDocuments.map((document) => (
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
                                        {document.type_label}
                                    </div>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            statusVariant[document.status] ??
                                            'secondary'
                                        }
                                    >
                                        {document.status_label}
                                    </Badge>
                                    {permissions.canManageBots ? (
                                        <Form
                                            {...documents.destroy.form({
                                                current_team: currentTeam.slug,
                                                bot: bot.id,
                                                document: document.id,
                                            })}
                                        >
                                            {({ processing }) => (
                                                <Button
                                                    type="submit"
                                                    variant="ghost"
                                                    size="icon"
                                                    data-test="delete-document-button"
                                                    disabled={processing}
                                                    aria-label="Remove document"
                                                >
                                                    <Trash2 />
                                                </Button>
                                            )}
                                        </Form>
                                    ) : null}
                                </div>
                            </div>
                        ))}

                        {botDocuments.length === 0 ? (
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
