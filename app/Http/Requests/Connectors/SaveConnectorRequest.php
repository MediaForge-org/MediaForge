<?php

declare(strict_types=1);

namespace App\Http\Requests\Connectors;

use App\Connectors\Sdk\Support\BaseUrl;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a connector configuration submission. The route is already
 * auth-protected; here we only guard the payload. An empty `secret` is allowed
 * and means "keep the stored credential" (SaveConnectorConfig handles that).
 */
final class SaveConnectorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|Closure>> */
    public function rules(): array
    {
        return [
            'base_url' => [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (!is_string($value) || !BaseUrl::isValid($value)) {
                        $fail('Enter a valid http or https URL.');
                    }
                },
            ],
            'secret' => ['nullable', 'string', 'max:4096'],
            'clear_secret' => ['sometimes', 'boolean'],
        ];
    }
}
