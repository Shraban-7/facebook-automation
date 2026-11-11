<?php

namespace App\Http\Requests\Facebook;

use App\Models\FacebookPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAutoReplyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $pageId = $this->integer('facebook_page_id');
        if ($pageId <= 0) {
            return true;
        }

        return FacebookPage::query()
            ->where('id', $pageId)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'facebook_page_id' => ['required', 'integer', 'exists:facebook_pages,id'],
            'name' => ['required', 'string', 'max:120'],
            'channel' => ['required', 'string', Rule::in(['messenger', 'comment', 'both'])],
            'trigger_type' => ['required', 'string', Rule::in(['keyword', 'exact', 'contains', 'regex', 'fallback'])],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
            'banned_words' => ['nullable', 'array'],
            'banned_words.*' => ['string', 'max:100'],
            'reply_template' => ['required', 'string', 'max:2000'],
            'private_reply_template' => ['nullable', 'string', 'max:2000'],
            'moderation_action' => ['required', 'string', Rule::in(['none', 'hide'])],
            'respect_24h_window' => ['required', 'boolean'],
            'cooldown_seconds' => ['required', 'integer', 'min:0', 'max:86400'],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['required', 'boolean'],
        ];
    }
}
