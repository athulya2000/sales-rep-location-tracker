<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

final class ZoneReportRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $coordinates = $this->query('coordinates') ?? $this->input('coordinates');

        if (is_string($coordinates)) {
            $decoded = json_decode($coordinates, true);
            if (is_array($decoded)) {
                $this->merge(['coordinates' => $decoded]);
            }
        }
    }

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
            'date' => ['required', 'date_format:Y-m-d'],
            'coordinates' => ['required', 'array', 'min:3'],
            'coordinates.*' => ['required', 'array', 'size:2'],
            'coordinates.*.0' => ['required', 'numeric', 'between:-90,90'],
            'coordinates.*.1' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => 'The date parameter is required.',
            'date.date_format' => 'The date must match the format YYYY-MM-DD.',
            'coordinates.required' => 'The polygon coordinates are required.',
            'coordinates.min' => 'A valid polygon zone requires at least 3 coordinate vertices.',
            'coordinates.*.size' => 'Each coordinate must contain exactly [latitude, longitude].',
        ];
    }
}
