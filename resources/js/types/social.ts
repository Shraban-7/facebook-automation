export type SocialProviderKey =
    | 'facebook'
    | 'instagram'
    | 'threads'
    | 'linkedin'
    | 'youtube'
    | 'fake';

export interface ProviderCapabilities {
    text: boolean;
    images: boolean;
    video: boolean;
    carousel: boolean;
    link: boolean;
    max_text_length: number;
    max_images: number;
    max_video_seconds: number;
    supports_native_scheduling: boolean;
    supports_messaging: boolean;
    supports_comments: boolean;
    supports_analytics: boolean;
    supports_webhooks: boolean;
    requires_public_media_url: boolean;
}

export interface SocialAccountSummary {
    id: number;
    provider: SocialProviderKey;
    provider_label: string;
    kind: string;
    external_id?: string;
    display_name: string;
    handle: string | null;
    avatar_url?: string | null;
    status?: string;
    reauth_required?: boolean;
    last_synced_at?: string | null;
    capabilities?: ProviderCapabilities;
}

export interface WorkspaceSummary {
    id: number;
    name: string;
    slug: string;
    plan: string;
}

export interface ContentTargetItem {
    id: number;
    social_account_id: number;
    provider: SocialProviderKey;
    account_name: string;
    status: string;
    scheduled_at: string | null;
    published_at: string | null;
    permalink: string | null;
    last_error_message: string | null;
    attempt_count: number;
}

export interface ContentItem {
    id: number;
    title: string | null;
    body: string | null;
    link_url: string | null;
    status: string;
    scheduled_at: string | null;
    created_at: string | null;
    targets: ContentTargetItem[];
}
