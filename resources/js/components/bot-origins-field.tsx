import { Plus, X } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type Props = {
    defaultOrigins?: string[];
    errors: Record<string, string>;
};

let rowId = 0;

const makeRow = (value: string) => ({ key: rowId++, value });

export default function BotOriginsField({
    defaultOrigins = [],
    errors,
}: Props) {
    const [rows, setRows] = useState(() =>
        (defaultOrigins.length > 0 ? defaultOrigins : ['']).map(makeRow),
    );

    const updateRow = (key: number, value: string) => {
        setRows((current) =>
            current.map((row) => (row.key === key ? { ...row, value } : row)),
        );
    };

    const addRow = () => setRows((current) => [...current, makeRow('')]);

    const removeRow = (key: number) =>
        setRows((current) => current.filter((row) => row.key !== key));

    return (
        <div className="grid gap-2">
            <Label>Allowed widget origins</Label>
            <p className="text-sm text-muted-foreground">
                Websites that may embed this bot's chat widget, e.g.{' '}
                <code>https://example.com</code>.
            </p>

            <div className="space-y-2">
                {rows.map((row, index) => (
                    <div key={row.key} className="space-y-1">
                        <div className="flex items-center gap-2">
                            <Input
                                name="embed_origins[]"
                                data-test="bot-origin-input"
                                value={row.value}
                                onChange={(event) =>
                                    updateRow(row.key, event.target.value)
                                }
                                placeholder="https://example.com"
                                autoComplete="off"
                            />
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                data-test="bot-origin-remove"
                                onClick={() => removeRow(row.key)}
                                aria-label="Remove origin"
                            >
                                <X className="h-4 w-4" />
                            </Button>
                        </div>
                        <InputError
                            message={errors[`embed_origins.${index}`]}
                        />
                    </div>
                ))}
            </div>

            <div>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    data-test="bot-origin-add"
                    onClick={addRow}
                >
                    <Plus className="h-4 w-4" /> Add origin
                </Button>
            </div>
        </div>
    );
}
