<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $rows = $this->input('items', []);
        if (is_array($rows)) {
            $this->merge(['items' => array_values(array_filter($rows, fn ($r) => ! is_array($r) || count(array_filter($r, fn ($v) => $v !== null && $v !== '')) > 0))]);
        }
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('user_id', $this->user()->id)],
            'title' => ['required', 'string', 'max:120'], 'issue_date' => ['required', 'date_format:Y-m-d'], 'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'tax_rate' => ['required', 'integer', 'regex:/^(0|10)$/'], 'version' => [$this->route('invoice') ? 'required' : 'nullable', 'integer', 'min:1'],
            'items' => ['required', 'array', 'min:1', 'max:20'], 'items.*' => ['array:description,quantity,unit_price'],
            'items.*.description' => ['required', 'string', 'max:120'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000', 'regex:/^[0-9]+$/'],
            'items.*.unit_price' => ['required', 'integer', 'min:0', 'max:1000000', 'regex:/^[0-9]+$/'],
        ];
    }

    public function messages(): array
    {
        return ['required' => ':attributeを入力してください。', 'integer' => ':attributeは整数で入力してください。', 'items.required' => '明細を1行以上入力してください。', 'due_date.after_or_equal' => '支払期限は請求日以降にしてください。', 'customer_id.exists' => '選択できる顧客が見つかりません。'];
    }

    public function attributes(): array
    {
        return ['title' => '件名', 'customer_id' => '顧客', 'issue_date' => '請求日', 'due_date' => '支払期限', 'items.*.description' => '明細名', 'items.*.quantity' => '数量', 'items.*.unit_price' => '単価'];
    }
}
