<?php

namespace App\Http\Requests\HotDeal;

use Illuminate\Foundation\Http\FormRequest;

class HotDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Set to true or add admin check
    }

    public function rules(): array
    {
        $rules = [
            'from_airport' => 'required|string|exists:airports,iata_code',
            'to_airport' => 'required|string|exists:airports,iata_code|different:from_airport',
            'title' => 'required|string|max:255',
            'tag' => 'nullable|string|max:100',
            'original_price' => 'required|numeric|min:0',
            'discounted_price' => 'required|numeric|min:0|lte:original_price',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'is_active' => 'boolean',
            'display_order' => 'integer|min:0',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'focus_keyword' => 'nullable|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'og_title' => 'nullable|string|max:255',
            'og_description' => 'nullable|string|max:500',
            'canonical_url' => 'nullable|url|max:500',
            'no_index' => 'boolean',
            'no_follow' => 'boolean',
        ];

        // For PUT/PATCH requests, make some fields optional
        if ($this->isMethod('PUT') || $this->isMethod('PATCH')) {
            $rules['from_airport'] = 'sometimes|required|string|exists:airports,iata_code';
            $rules['to_airport'] = 'sometimes|required|string|exists:airports,iata_code|different:from_airport';
            $rules['title'] = 'sometimes|required|string|max:255';
            $rules['original_price'] = 'sometimes|required|numeric|min:0';
            $rules['discounted_price'] = 'sometimes|required|numeric|min:0|lte:original_price';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'from_airport.required' => 'Please select a departure airport',
            'from_airport.exists' => 'Selected departure airport does not exist',
            'to_airport.required' => 'Please select an arrival airport',
            'to_airport.exists' => 'Selected arrival airport does not exist',
            'to_airport.different' => 'Departure and arrival airports must be different',
            'title.required' => 'Title is required',
            'original_price.required' => 'Original price is required',
            'original_price.min' => 'Original price must be at least 0',
            'discounted_price.required' => 'Discounted price is required',
            'discounted_price.min' => 'Discounted price must be at least 0',
            'discounted_price.lte' => 'Discounted price must be less than or equal to original price',
            'image.image' => 'File must be an image',
            'image.mimes' => 'Image must be jpeg, png, jpg, or webp format',
            'image.max' => 'Image size must not exceed 2MB',
            'end_date.after_or_equal' => 'End date must be after or equal to start date',
            'focus_keyword.max' => 'Focus keyword must not exceed 255 characters',
            'meta_title.max' => 'Meta title must not exceed 255 characters',
            'meta_description.max' => 'Meta description must not exceed 500 characters',
            'og_title.max' => 'OG title must not exceed 255 characters',
            'og_description.max' => 'OG description must not exceed 500 characters',
            'canonical_url.url' => 'Canonical URL must be a valid URL',
            'canonical_url.max' => 'Canonical URL must not exceed 500 characters',
        ];
    }
}
