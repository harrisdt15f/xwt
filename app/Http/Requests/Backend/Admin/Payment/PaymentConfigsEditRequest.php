<?php

namespace App\Http\Requests\Backend\Admin\Payment;

use App\Http\Requests\BaseFormRequest;

/**
 * Class PaymentConfigsEditRequest
 * @package App\Http\Requests\Backend\Admin\Payment
 */
class PaymentConfigsEditRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return boolean
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'id' => 'required|numeric|exists:backend_payment_configs,id',//ID
            'payment_vendor_id' => 'required|numeric|exists:backend_payment_vendors,id',//支付厂商的id
            'payment_type_id' => 'required|numeric|exists:backend_payment_types,id',//支付类型的id
            'payment_name' => 'required|string',//支付方式名称
            'payment_sign' => 'required|string',//支付方式标记
            'request_url' => 'required|string',//支付方式请求地址
            'request_mode' => 'required|in:0,1',//支付的请求方式 0 jump 1 json
            'direction' => 'required|in:0,1',//金流的方向 1 入款 0 出款
            'status' => 'required|in:0,1',//状态 1 上架 0 下架
        ];
    }
}
