<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return ['amount' => ['required', 'integer', 'min:1', 'max:1000000000', 'regex:/^[0-9]+$/'], 'paid_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'], 'request_token' => ['required', 'uuid'], 'note' => ['nullable', 'string', 'max:120']];
    }

    public function messages(): array
    {
        return ['amount.*' => '入金額は1〜10億円の整数で指定してください。', 'paid_on.*' => '入金日は有効な日付で、今日以前を指定してください。', 'request_token.*' => '送信IDが不正です。画面を再表示してください。'];
    }
}
