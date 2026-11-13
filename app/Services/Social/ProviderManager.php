<?php

namespace App\Services\Social;

use App\Contracts\Social\SocialProvider;
use App\Services\Social\Providers\Facebook\FacebookProvider;
use App\Services\Social\Providers\Fake\FakeProvider;
use App\Services\Social\Providers\Instagram\InstagramProvider;
use App\Services\Social\Providers\LinkedIn\LinkedInProvider;
use App\Services\Social\Providers\Threads\ThreadsProvider;
use App\Services\Social\Providers\YouTube\YouTubeProvider;
use Illuminate\Support\Manager;

class ProviderManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return 'facebook';
    }

    public function provider(string $name): SocialProvider
    {
        /** @var SocialProvider $driver */
        $driver = $this->driver($name);

        return $driver;
    }

    protected function createFacebookDriver(): SocialProvider
    {
        return $this->container->make(FacebookProvider::class);
    }

    protected function createInstagramDriver(): SocialProvider
    {
        return $this->container->make(InstagramProvider::class);
    }

    protected function createThreadsDriver(): SocialProvider
    {
        return $this->container->make(ThreadsProvider::class);
    }

    protected function createLinkedinDriver(): SocialProvider
    {
        return $this->container->make(LinkedInProvider::class);
    }

    protected function createYoutubeDriver(): SocialProvider
    {
        return $this->container->make(YouTubeProvider::class);
    }

    protected function createFakeDriver(): SocialProvider
    {
        return $this->container->make(FakeProvider::class);
    }
}
