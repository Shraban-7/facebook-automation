<?php

namespace App\Http\Controllers\Facebook;

use App\Http\Controllers\Controller;
use App\Http\Requests\Facebook\StorePostRequest;
use App\Http\Requests\Facebook\UpdatePostRequest;
use App\Jobs\PublishScheduledPostJob;
use App\Models\FacebookPage;
use App\Models\Post;
use App\Models\ScheduledPost;
use App\Models\User;
use App\Services\Facebook\PostSchedulerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PostSchedulerController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();
        $activePage = $user->resolveCurrentPage();

        $posts = $activePage !== null
            ? Post::query()
                ->with('scheduledPost')
                ->where('facebook_page_id', $activePage->id)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Post $post): array => [
                    'id' => $post->id,
                    'facebook_page_id' => $post->facebook_page_id,
                    'facebook_post_id' => $post->facebook_post_id,
                    'type' => $post->type,
                    'content' => $post->content,
                    'media_urls' => $post->media_urls ?? [],
                    'link_url' => $post->link_url,
                    'link_title' => $post->link_title,
                    'status' => $post->status,
                    'published_at' => $post->published_at?->toIso8601String(),
                    'permalink_url' => $post->permalink_url,
                    'metrics' => $post->metrics,
                    'last_error' => $post->last_error,
                    'created_at' => $post->created_at?->toIso8601String(),
                    'schedule' => $post->scheduledPost ? [
                        'id' => $post->scheduledPost->id,
                        'scheduled_for' => $post->scheduledPost->scheduled_for->toIso8601String(),
                        'timezone' => $post->scheduledPost->timezone,
                        'status' => $post->scheduledPost->status,
                        'attempts' => $post->scheduledPost->attempts,
                        'max_attempts' => $post->scheduledPost->max_attempts,
                        'last_attempted_at' => $post->scheduledPost->last_attempted_at?->toIso8601String(),
                        'next_retry_at' => $post->scheduledPost->next_retry_at?->toIso8601String(),
                        'error_code' => $post->scheduledPost->error_code,
                        'error_message' => $post->scheduledPost->error_message,
                        'failure_log' => $post->scheduledPost->failure_log ?? [],
                    ] : null,
                ])
            : collect();

        return Inertia::render('facebook/scheduler', [
            'activePage' => $activePage ? [
                'id' => $activePage->id,
                'name' => $activePage->name,
                'page_id' => $activePage->page_id,
                'avatar_url' => $activePage->avatar_url,
                'status' => $activePage->status,
            ] : null,
            'posts' => $posts,
        ]);
    }

    public function store(StorePostRequest $request, PostSchedulerService $schedulerService): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $validated = $request->validated();

        $page = FacebookPage::query()
            ->where('id', (int) $validated['facebook_page_id'])
            ->where('user_id', $user->id)
            ->firstOrFail();

        $post = $schedulerService->createPost($user, $page, $validated);

        $message = match ($post->status) {
            'draft' => 'Post saved to drafts.',
            default => 'Post scheduled and queued for publishing.',
        };

        return back()->with('success', $message);
    }

    public function update(UpdatePostRequest $request, Post $post, PostSchedulerService $schedulerService): RedirectResponse
    {
        $schedulerService->updatePost($post, $request->validated());

        return back()->with('success', 'Post updated.');
    }

    public function destroy(Post $post): RedirectResponse
    {
        Gate::authorize('delete', $post);

        $post->scheduledPost()
            ->whereIn('status', ['pending', 'failed'])
            ->update(['status' => 'cancelled']);

        $post->delete();

        return back()->with('success', 'Post deleted.');
    }

    /**
     * Retry a failed scheduled post immediately via the background queue.
     */
    public function retry(ScheduledPost $scheduledPost): RedirectResponse
    {
        $post = $scheduledPost->post;
        if ($post !== null) {
            Gate::authorize('update', $post);
        }

        $scheduledPost->update([
            'status' => 'pending',
            'attempts' => 0,
            'next_retry_at' => null,
            'error_code' => null,
            'error_message' => null,
        ]);

        $post?->update([
            'status' => 'scheduled',
            'last_error' => null,
        ]);

        PublishScheduledPostJob::dispatch($scheduledPost->id);

        return back()->with('success', 'Scheduled post re-queued for publishing.');
    }
}
