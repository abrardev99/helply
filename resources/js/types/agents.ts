export type AgentStatus = 'active' | 'paused';

export type Agent = {
    id: string;
    name: string;
    status: AgentStatus;
    status_label: string;
    embed_origins: string[];
    embedding_model: string;
    chat_model: string;
    system_prompt: string | null;
    confidence_threshold: number;
    documents_count?: number;
};

export type AgentDocument = {
    id: string;
    title: string | null;
    type: string;
    type_label: string;
    source_url: string | null;
    status: string;
    status_label: string;
};

export type AgentPermissions = {
    canManageAgents: boolean;
};

export type StatusOption = {
    value: AgentStatus;
    label: string;
};
