<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\WithinWorkTime;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Str;

class ApplicationRequest extends FormRequest
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
        $workStart = $this->input('work_start');
        $workEnd = $this->input('work_end');

        $restRules = [];
        $restInputs = $this->input('rest_start', []);

        foreach (array_keys($restInputs) as $key) {
             $restRules["rest_start.{$key}"] = [
                 'nullable',
                 'date_format:H:i',
                 new WithinWorkTime($workStart, $workEnd, '休憩開始時間'),
                 "before_or_equal:rest_end.{$key}",
             ];
             $restRules["rest_end.{$key}"] = [
                 'nullable',
                 'date_format:H:i',
                 new WithinWorkTime($workStart, $workEnd, '休憩終了時間'),
                 "after_or_equal:rest_start.{$key}",
             ];
        }


        return array_merge([
            'work_start' => 'before_or_equal:work_end',
            'remarks' => 'required',
            'rest_start' => 'array',
            'rest_end' => 'array',

        ], $restRules);
    }

    public function messages()
    {
        $messages = [
            'work_start.before_or_equal' => '出勤時間もしくは退勤時間が不適切な値です',
            'remarks.required' => '備考を記入してください',
        ];

         $restInputs = $this->input('rest_start', []);
         foreach (array_keys($restInputs) as $key) {
             $messages["rest_start.{$key}.before_or_equal"] = ($key + 1) . "番目の休憩開始時間は休憩終了時間より前に設定してください。";
             $messages["rest_end.{$key}.after_or_equal"] = ($key + 1) . "番目の休憩終了時間は休憩開始時間より後に設定してください。";
        }

        return $messages;
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $errors = $validator->errors();
            $workStart = $this->input('work_start');
            $workEnd = $this->input('work_end');
            $withinWorkTimeMsgIdentifier = '休憩時間が勤務時間外です';
            $restIndexesWithRangeError = [];

            foreach ($errors->keys() as $key) {
                if (preg_match('/^rest_(start|end)\.(\d+)$/', $key, $matches)) {
                    $index = $matches[2];
                    foreach ($errors->get($key) as $message) {
                        if (Str::contains($message, $withinWorkTimeMsgIdentifier)) {
                            if (!isset($restIndexesWithRangeError[$index])) {
                                $restIndexesWithRangeError[$index] = true;
                                $consolidatedMessage = (new WithinWorkTime($workStart, $workEnd))->message();
                                $validator->errors()->add("rest_time_range.{$index}", $consolidatedMessage);
                            }
                        }
                    }
                }
            }
        });
    }
}
