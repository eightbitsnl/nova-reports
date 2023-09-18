<?php

namespace Eightbitsnl\NovaReports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReportPreviewRequest extends FormRequest
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
            "relations" => ['required', "array"],
            "query" => ['required', "array"],
        ];
    }
}
