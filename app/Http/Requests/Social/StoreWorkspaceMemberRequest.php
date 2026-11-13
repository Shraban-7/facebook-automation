<?php

namespace App\Http\Requests\Social;

use App\Enums\WorkspaceRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWorkspaceMemberRequest extends FormRequest
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
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::in([
                WorkspaceRole::Admin->value,
                WorkspaceRole::Editor->value,
                WorkspaceRole::Viewer->value,
            ])],
        ];
    }
}
