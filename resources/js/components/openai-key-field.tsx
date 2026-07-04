import { Form, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import openaiKey from '@/routes/teams/openai-key';

type Props = {
    teamSlug: string;
    hasOpenAiKey: boolean;
};

export default function OpenAiKeyField({ teamSlug, hasOpenAiKey }: Props) {
    const [editing, setEditing] = useState(!hasOpenAiKey);

    const removeKey = () => {
        router.delete(openaiKey.destroy(teamSlug), { preserveScroll: true });
    };

    return (
        <div className="space-y-6">
            <Heading
                variant="small"
                title="OpenAI API key"
                description="Used server-side for embeddings and chat. It's encrypted at rest and never shown again after saving."
            />

            {hasOpenAiKey && !editing ? (
                <div className="flex max-w-xl items-center justify-between gap-4 rounded-lg border p-4">
                    <div className="min-w-0">
                        <p className="font-medium">Key is set</p>
                        <p className="font-mono text-sm text-muted-foreground">
                            ••••••••••••••••
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="secondary"
                            data-test="replace-openai-key-button"
                            onClick={() => setEditing(true)}
                        >
                            Replace
                        </Button>
                        <Button
                            variant="ghost"
                            data-test="remove-openai-key-button"
                            onClick={removeKey}
                        >
                            Remove
                        </Button>
                    </div>
                </div>
            ) : (
                <Form
                    {...openaiKey.update.form(teamSlug)}
                    onSuccess={() => setEditing(false)}
                    resetOnSuccess
                    className="max-w-xl space-y-2"
                >
                    {({ errors, processing }) => (
                        <>
                            <Label htmlFor="openai_api_key" className="sr-only">
                                OpenAI API key
                            </Label>
                            <div className="flex gap-2">
                                <Input
                                    id="openai_api_key"
                                    name="openai_api_key"
                                    type="password"
                                    autoComplete="off"
                                    data-test="openai-key-input"
                                    placeholder="sk-..."
                                    required
                                />
                                <Button
                                    type="submit"
                                    data-test="save-openai-key-button"
                                    disabled={processing}
                                >
                                    Save
                                </Button>
                                {hasOpenAiKey ? (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setEditing(false)}
                                    >
                                        Cancel
                                    </Button>
                                ) : null}
                            </div>
                            <InputError message={errors.openai_api_key} />
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
