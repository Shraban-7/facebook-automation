<?php

namespace App\Http\Requests\Facebook;

use App\Models\AutoReplyRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAutoReplyRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AutoReplyRule|null $rule */
        $rule = $this->route('autoReplyRule');
        $user = $this->user();

        return $user !== null && $rule !== null && $user->can('update', $rule);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
