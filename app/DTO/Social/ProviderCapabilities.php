<?php

namespace App\DTO\Social;

use JsonSerializable;

final readonly class ProviderCapabilities implements JsonSerializable
{
    public function __construct(
        public bool $text = true,
        public bool $images = false,
        public bool $video = false,
        public bool $carousel = false,
        public bool $link = false,
        public int $maxTextLength = 2000,
        public int $maxImages = 1,
        public int $maxVideoSeconds = 0,
        public bool $supportsNativeScheduling = false,
        public bool $supportsMessaging = false,
        public bool $supportsComments = false,
        public bool $supportsAnalytics = false,
        public bool $supportsWebhooks = false,
        public bool $requiresPublicMediaUrl = false,
        public bool $requiresTitle = false,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'images' => $this->images,
            'video' => $this->video,
            'carousel' => $this->carousel,
            'link' => $this->link,
            'max_text_length' => $this->maxTextLength,
            'max_images' => $this->maxImages,
            'max_video_seconds' => $this->maxVideoSeconds,
            'supports_native_scheduling' => $this->supportsNativeScheduling,
            'supports_messaging' => $this->supportsMessaging,
            'supports_comments' => $this->supportsComments,
            'supports_analytics' => $this->supportsAnalytics,
            'supports_webhooks' => $this->supportsWebhooks,
            'requires_public_media_url' => $this->requiresPublicMediaUrl,
            'requires_title' => $this->requiresTitle,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
