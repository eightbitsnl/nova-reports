<?php

namespace Eightbitsnl\NovaReports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportWebPreviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            "entrypoint" => ['required', "string"],
            "export_fields" => ['nullable', "array"],
            "loadrelation" => ['nullable', "array"],
            "query" => ['nullable', "array"],
        ];
    }
}
