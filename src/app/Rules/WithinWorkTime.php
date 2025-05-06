<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Carbon\Carbon;

class WithinWorkTime implements Rule
{
    private $workStart;
    private $workEnd;
    private $fieldName;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($workStart, $workEnd, $fieldName = '休憩時間')
    {
        $this->workStart = $workStart;
        $this->workEnd = $workEnd;
        $this->fieldName = $fieldName;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        if (empty($value) || empty($this->workStart) || empty($this->workEnd)) {
            return true;
        }

        try {
            $restTime = Carbon::parse($value);
            $startTime = Carbon::parse($this->workStart);
            $endTime = Carbon::parse($this->workEnd);

            return $restTime->gte($startTime) && $restTime->lte($endTime);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return "休憩時間が勤務時間外です";
    }
}
