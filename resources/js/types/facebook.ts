export interface FacebookPageSummary {
    id: number;
    page_id: string;
    name: string;
    category: string | null;
    avatar_url: string | null;
    followers_count: number;
    status: 'active' | 'expired' | 'revoked' | 'rate_limited' | 'disconnected' | string;
    webhook_subscribed?: boolean;
    token_expires_at?: string | null;
    token_last_refreshed_at?: string | null;
    is_expired?: boolean;
    is_expiring_soon?: boolean;
    last_error_code?: number | null;
    last_error_message?: string | null;
    last_synced_at?: string | null;
    permissions?: string[];
    tasks?: string[];
    counts?: {
        posts: number;
        scheduled_posts: number;
        auto_reply_rules: number;
        messages: number;
    };
}

export interface FailureLogEntry {
    attempt: number;
    timestamp: string;
    graph_code?: number;
    graph_subcode?: number | null;
    http_status?: number;
    category?: string;
    message: string;
    remediation?: string;
    backoff_seconds?: number | null;
    next_retry_at?: string | null;
}

export interface ScheduledPostItem {
    id: number;
    post_id?: number;
    scheduled_for: string;
    timezone: string;
    status: 'pending' | 'processing' | 'published' | 'failed' | 'cancelled' | string;
    attempts: number;
    max_attempts: number;
    last_attempted_at?: string | null;
    next_retry_at?: string | null;
    error_code?: number | null;
    error_message?: string | null;
    failure_log?: FailureLogEntry[];
    post?: PostItem;
}

export interface PostItem {
    id: number;
    facebook_page_id: number;
    facebook_post_id?: string | null;
    type: 'text' | 'image' | 'link';
    content: string;
    media_urls?: string[];
    link_url?: string | null;
    link_title?: string | null;
    status: 'draft' | 'scheduled' | 'publishing' | 'published' | 'failed' | string;
    published_at?: string | null;
    permalink_url?: string | null;
    metrics?: {
        reach?: number;
        engagements?: number;
        reactions?: number;
        comments?: number;
        shares?: number;
    } | null;
    last_error?: string | null;
    created_at?: string | null;
    schedule?: ScheduledPostItem | null;
}

export interface AutoReplyRuleItem {
    id: number;
    facebook_page_id: number;
    name: string;
    channel: 'messenger' | 'comment' | 'both';
    trigger_type: 'keyword' | 'exact' | 'contains' | 'regex' | 'fallback';
    keywords: string[] | null;
    banned_words: string[] | null;
    reply_template: string;
    private_reply_template?: string | null;
    moderation_action: 'none' | 'hide';
    respect_24h_window: boolean;
    cooldown_seconds: number;
    priority: number;
    is_active: boolean;
    match_count: number;
    last_triggered_at?: string | null;
}

export interface MessageItem {
    id: number;
    channel: 'messenger' | 'comment';
    direction: 'inbound' | 'outbound';
    external_id?: string | null;
    sender_id: string;
    sender_name?: string | null;
    message_text?: string | null;
    reply_text?: string | null;
    is_hidden: boolean;
    status:
        | 'received'
        | 'queued'
        | 'replied'
        | 'moderated'
        | 'skipped_outside_window'
        | 'skipped_policy'
        | 'failed'
        | string;
    within_24h_window?: boolean;
    last_user_interaction_at?: string | null;
    replied_at?: string | null;
    error_message?: string | null;
    rule_name?: string | null;
    auto_reply_rule?: {
        id: number;
        name: string;
    } | null;
    created_at?: string | null;
}

export interface InsightPoint {
    date: string;
    iso_date: string;
    reach: number;
    impressions: number;
    engagement: number;
    followers: number;
    net_followers: number;
    page_views: number;
}

export interface AnalyticsPayload {
    days: number;
    summary: {
        total_reach: number;
        total_impressions: number;
        total_engagements: number;
        engagement_rate: number;
        followers: number;
        net_followers: number;
        page_views: number;
    };
    series: InsightPoint[];
    top_posts: Array<{
        id: number;
        facebook_post_id?: string | null;
        type: string;
        content: string;
        published_at?: string | null;
        permalink_url?: string | null;
        reach: number;
        engagements: number;
        reactions: number;
        comments: number;
        shares: number;
    }>;
    cached_at: string;
}

export interface RateLimitStatus {
    calls_used: number;
    hourly_limit: number;
    remaining: number;
    usage_percent: number;
    header_usage: {
        call_count: number;
        total_cputime: number;
        total_time: number;
    };
    header_peak_percent: number;
    is_throttled: boolean;
    resets_at: string;
}

export interface ActivityLogItem {
    id: number;
    category: string;
    action: string;
    level: 'info' | 'warning' | 'error' | 'critical' | string;
    method?: string | null;
    endpoint?: string | null;
    http_status?: number | null;
    graph_error_code?: number | null;
    graph_error_subcode?: number | null;
    duration_ms?: number | null;
    rate_limit_usage?: {
        call_count?: number;
        total_cputime?: number;
        total_time?: number;
    } | null;
    message: string;
    context?: Record<string, unknown> | null;
    created_at: string;
    facebook_page?: {
        id: number;
        name: string;
        page_id: string;
    } | null;
}

export interface WebhookEventItem {
    id: number;
    event_id: string;
    object: string;
    entry_id: string;
    event_type: string;
    signature?: string | null;
    signature_verified: boolean;
    payload: Record<string, unknown>;
    status: string;
    attempts: number;
    processed_at?: string | null;
    error_message?: string | null;
    created_at: string;
}
