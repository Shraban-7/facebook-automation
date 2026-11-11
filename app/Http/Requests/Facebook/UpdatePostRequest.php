<?php

namespace App\Http\Requests\Facebook;

use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Post|null $post */
        $post = $this->route('post');
        $user = $this->user();

        return $user !== null && $post !== null && $user->can('update', $post);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'required', 'string', Rule::in(['text', 'image', 'link'])],
            'content' => ['required', 'string', 'max:5000'],
            'media_urls' => ['nullable', 'array'],
            'media_urls.*' => ['required', 'url', 'max:1024'],
            'link_url' => ['nullable', 'url', 'max:1024'],
            'link_title' => ['nullable', 'string', 'max:255'],
            'scheduled_for' => ['nullable', 'date'],
            'timezone' => ['nullable', 'string', 'max:64'],
            'save_as_draft' => ['nullable', 'boolean'],
        ];
    }
}
