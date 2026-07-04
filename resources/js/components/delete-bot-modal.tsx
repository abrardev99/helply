import { Form, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy } from '@/routes/bots';
import type { Bot } from '@/types';

type Props = {
    bot: Pick<Bot, 'id' | 'name'>;
    open: boolean;
    onOpenChange: (open: boolean) => void;
};

export default function DeleteBotModal({ bot, open, onOpenChange }: Props) {
    const currentTeam = usePage().props.currentTeam;

    if (!currentTeam) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <Form
                    key={String(open)}
                    {...destroy.form({
                        current_team: currentTeam.slug,
                        bot: bot.id,
                    })}
                    onSuccess={() => onOpenChange(false)}
                >
                    {({ processing }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Delete bot?</DialogTitle>
                                <DialogDescription>
                                    This permanently deletes{' '}
                                    <strong>"{bot.name}"</strong> along with all
                                    its documents, content, and conversations.
                                    This cannot be undone.
                                </DialogDescription>
                            </DialogHeader>

                            <DialogFooter className="mt-6 gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    variant="destructive"
                                    type="submit"
                                    data-test="delete-bot-confirm"
                                    disabled={processing}
                                >
                                    Delete bot
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
