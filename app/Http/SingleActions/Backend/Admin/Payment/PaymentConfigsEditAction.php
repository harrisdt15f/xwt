<?php

namespace App\Http\SingleActions\Backend\Admin\Payment;

use App\Http\Controllers\BackendApi\Admin\Payment\PaymentConfigsController;
use App\Models\Admin\Payment\BackendPaymentConfig;
use App\Models\Admin\Payment\BackendPaymentType;
use App\Models\Admin\Payment\BackendPaymentVendor;
use App\Models\Admin\Payment\PaymentInfo;
use Illuminate\Http\JsonResponse;
use App\Lib\BaseCache;
use Illuminate\Support\Facades\DB;
use Exception;

/**
 * Class PaymentConfigsEditAction
 * @package App\Http\SingleActions\Backend\Admin\Payment
 */
class PaymentConfigsEditAction
{
    use BaseCache;

    /**
     * @var BackendPaymentConfig $model 支付配置信息模型.
     */
    protected $model;

    /**
     * PaymentConfigsEditAction constructor.
     * @param BackendPaymentConfig $backendPaymentConfig 支付配置信息模型.
     */
    public function __construct(BackendPaymentConfig $backendPaymentConfig)
    {
        $this->model = $backendPaymentConfig;
    }

    /**
     * 编辑支付方式详情表
     * @param PaymentConfigsController $contll     主控制器.
     * @param array                    $inputDatas 前端获取编辑信息.
     * @return JsonResponse
     * @throws \Exception 异常.
     */
    public function execute(PaymentConfigsController $contll, array $inputDatas): JsonResponse
    {
        DB::beginTransaction();
        try {
            //根据用户选择获取需要的参数
            $payment_vendor = BackendPaymentVendor::find($inputDatas['payment_vendor_id']);
            $payment_type = BackendPaymentType::find($inputDatas['payment_type_id']);
            $addDatas = [];
            $addDatas['payment_vendor_name'] = $payment_vendor->payment_vendor_name;
            $addDatas['payment_vendor_sign'] = $payment_vendor->payment_vendor_sign;
            $addDatas['payment_type_name'] = $payment_type->payment_type_name;
            $addDatas['payment_type_sign'] = $payment_type->payment_type_sign;
            $addDatas['banks_code'] = [
                'name' => $payment_type->payment_type_name,
                'ico' => $payment_type->payment_ico,
                'code' => $payment_type->is_bank,
            ];
            $addDatas['banks_code'] = json_encode($addDatas['banks_code']);
            $inputDatas = array_merge($inputDatas, $addDatas);
            //执行添加操作
            if (!empty($inputDatas['id']) && isset($inputDatas['id'])) {
                $payment_config = BackendPaymentConfig::find($inputDatas['id']);
                $payment_config->fill($inputDatas);
                $payment_config->save();
                //更新支付方式详情表信息
                $updateDatas = [];
                $updateDatas['direction'] = $payment_config->direction;
                $updateDatas['payment_name'] = $payment_config->payment_name;
                $updateDatas['payment_sign'] = $payment_config->payment_sign;
                $updateDatas['payment_vendor_sign'] = $payment_config->payment_vendor_sign;
                $updateDatas['payment_type_sign'] = $payment_config->payment_type_sign;
                $updateDatas['request_url'] = $payment_config->request_url;
                $updateDatas['back_url'] = rtrim(configure('back_url'), '/') . '/' . $updateDatas['direction'] . '/' . ltrim($updateDatas['payment_sign'], '/');//支付方式的返回地址
                //执行更新支付方式详情表信息
                PaymentInfo::where('config_id', $inputDatas['id'])->update($updateDatas);
                DB::commit();
            }
            return $contll->msgOut(true);
        } catch (Exception $e) {
            DB::rollBack();
            return $contll->msgOut(false, [], $e->getCode(), $e->getMessage());
        }
    }
}
