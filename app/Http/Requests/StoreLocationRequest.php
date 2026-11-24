<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
   public function authorize(): bool { return true; }

    // Single point aaye to usse batch format me convert kar do
    protected function prepareForValidation(): void
    {
        if (! $this->has('locations') && $this->has('latitude')) {
            $this->merge([
                'locations' => [$this->only(['latitude', 'longitude', 'accuracy', 'recorded_at'])],
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'locations' => ['required', 'array', 'min:1', 'max:500'],
            'locations.*.latitude' => ['required', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['required', 'numeric', 'between:-180,180'],
            'locations.*.accuracy' => ['required', 'numeric', 'min:0', 'max:10000'],
            'locations.*.recorded_at' => [
                'required',
                'date',
                'before_or_equal:+5 minutes',   // future ka timestamp nahi
                'after:-7 days',                // bahut purana nahi
            ],
        ];
    }
}
