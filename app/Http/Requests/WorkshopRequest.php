<?php

namespace App\Http\Requests;

use App\Enums\WorkshopFormat;
use App\Models\Workshop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkshopRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        /** @var Workshop|null $workshop */
        $workshop = $this->route('workshop');

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            // a new workshop can't start in the past; an existing one keeps its (possibly past) date
            'date' => ['required', 'date_format:Y-m-d', ...($workshop ? [] : ['after_or_equal:today'])],
            'time' => ['required', 'date_format:H:i'],
            'format' => ['required', Rule::enum(WorkshopFormat::class)],
            'place' => ['nullable', 'string', 'max:100'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'seats' => ['required', 'integer', 'min:'.max(1, $workshop?->seatsTaken() ?? 0), 'max:10000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'عنوان الورشة',
            'description' => 'الوصف',
            'date' => 'التاريخ',
            'time' => 'الوقت',
            'format' => 'النوع',
            'place' => 'المكان',
            'price' => 'السعر',
            'seats' => 'عدد المقاعد',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.after_or_equal' => 'تاريخ الورشة الجديدة لا يمكن أن يكون في الماضي.',
        ];
    }

    /**
     * The validated fields mapped to the model's columns (an empty place becomes "Zoom" online, "—" in person).
     *
     * @return array<string, mixed>
     */
    public function workshopAttributes(): array
    {
        $data = $this->safe()->except('time');

        return [
            ...$data,
            'start_time' => $this->validated('time'),
            'place' => filled($data['place'] ?? null)
                ? trim($data['place'])
                : ($this->enum('format', WorkshopFormat::class) === WorkshopFormat::Online ? 'Zoom' : '—'),
        ];
    }
}
