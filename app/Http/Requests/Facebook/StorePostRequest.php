<?php

namespace App\Http\Requests\Facebook;

use App\Models\FacebookPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(['text', 'image', 'link'])],
            'content' => ['required', 'string', 'max:5000'],
            'media_urls' => [
                'nullable',
                'array',
                Rule::when($this->input('type') === 'image', ['required', 'min:1']),
            ],
            'media_urls.*' => ['required', 'url', 'max:1024'],
            'link_url' => [
                'nullable',
                'url',
                'max:1024',
                Rule::when($this->input('type') === 'link', ['required']),
            ],
            'link_title' => ['nullable', 'string', 'max:255'],
            'scheduled_for' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'save_as_draft' => ['nullable', 'boolean'],
            'publish_now' => ['nullable', 'boolean'],
        ];
    }
}
