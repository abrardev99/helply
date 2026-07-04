import { Form, Head, usePage } from '@inertiajs/react';
import BotOriginsField from '@/components/bot-origins-field';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { create, index, store } from '@/routes/bots';

export default function BotsCreate() {
    const currentTeam = usePage().props.currentTeam;

    if (!currentTeam) {
        return null;
    }

    return (
        <>
            <Head title="New bot" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <Heading
                    title="New bot"
                    description="Give your bot a name and the websites allowed to embed it."
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
                                    data-test="bot-name-input"
                                    placeholder="Support bot"
                                    required
                                    autoFocus
                                />
                                <InputError message={errors.name} />
                            </div>

                            <BotOriginsField errors={errors} />

                            <div className="flex items-center gap-4">
                                <Button
                                    type="submit"
                                    data-test="bot-create-button"
                                    disabled={processing}
                                >
                                    Create bot
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </div>
        </>
    );
}

BotsCreate.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'Bots',
            href: props.currentTeam ? index(props.currentTeam.slug) : '/',
        },
        {
            title: 'New bot',
            href: props.currentTeam ? create(props.currentTeam.slug) : '/',
        },
    ],
});
