<?php

namespace App\Http\Requests\Social;

use Illuminate\Foundation\Http\FormRequest;

class StoreContentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'link_url' => ['nullable', 'url', 'max:2048'],
            'require_approval' => ['sometimes', 'boolean'],
            'media_ids' => ['sometimes', 'array'],
            'media_ids.*' => ['integer', 'exists:media_assets,id'],
            'targets' => ['required', 'array', 'min:1'],
            'targets.*.social_account_id' => ['required', 'integer', 'exists:social_accounts,id'],
            'targets.*.scheduled_at' => ['nullable', 'date'],
            'targets.*.body_override' => ['nullable', 'string', 'max:10000'],
            'targets.*.options' => ['nullable', 'array'],
        ];
    }
}
