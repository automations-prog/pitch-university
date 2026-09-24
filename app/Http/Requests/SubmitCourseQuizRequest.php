<?php

namespace App\Http\Requests;

use App\Models\CourseModule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitCourseQuizRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var CourseModule $module */
        $module = $this->route('module');

        $rules = ['answers' => ['required', 'array']];

        foreach ($module->quiz['questions'] as $question) {
            $rules["answers.{$question['id']}"] = ['required', 'integer', 'min:0', 'max:'.(count($question['choices']) - 1)];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'answers.required' => 'Please answer every question.',
            'answers.*.required' => 'Please answer every question.',
        ];
    }
}
