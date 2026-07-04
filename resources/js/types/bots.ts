export type BotStatus = 'active' | 'paused';

export type Bot = {
    id: string;
    name: string;
    status: BotStatus;
    status_label: string;
    embed_origins: string[];
    documents_count?: number;
};

export type BotDocument = {
    id: string;
    title: string | null;
    type: string;
    type_label: string;
    source_url: string | null;
    status: string;
    status_label: string;
};

export type BotPermissions = {
    canManageBots: boolean;
};

export type StatusOption = {
    value: BotStatus;
    label: string;
};
