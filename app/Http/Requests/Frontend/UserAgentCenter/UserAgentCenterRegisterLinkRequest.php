<?php

namespace App\Http\Requests\Frontend\UserAgentCenter;

use App\Http\Requests\BaseFormRequest;

class UserAgentCenterRegisterLinkRequest extends BaseFormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
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
            'expire' => 'required|integer',
            'channel'=>'required',
            'prize_group'=>'required|integer',
            'user_type'=>'required|in:2,3', // 2代理  3用户
        ];
    }
}
