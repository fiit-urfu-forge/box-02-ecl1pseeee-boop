<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UploadAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $maxKb = (int) config('digitalbank.avatar.max_size_kb', 5120);

        return [
            'avatar' => [
                'required',
                'file',
                'mimes:jpeg,jpg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                "max:{$maxKb}",
            ],
        ];
    }
}
