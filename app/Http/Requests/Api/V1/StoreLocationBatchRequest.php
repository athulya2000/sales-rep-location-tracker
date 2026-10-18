<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class StoreLocationBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'rep_id' => ['required', 'integer', 'exists:reps,id'],
            'points' => ['required', 'array', 'min:1', 'max:5000'],
            'points.*.lat' => ['required', 'numeric', 'between:-90,90'],
            'points.*.lng' => ['required', 'numeric', 'between:-180,180'],
            'points.*.recorded_at' => ['required', 'date', 'before_or_equal:+5 minutes'],
            'points.*.accuracy' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'rep_id.exists' => 'The selected sales representative does not exist.',
            'points.min' => 'At least one location point is required.',
            'points.max' => 'A location batch may not contain more than 5000 points.',
            'points.*.recorded_at.before_or_equal' => 'A recorded_at timestamp cannot be far in the future.',
        ];
    }
}
