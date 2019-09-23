<?php

namespace App\Models\User\Logics;

use App\Lib\Pay\BasePay;
use App\Models\User\FrontendUser;
use App\Models\User\Fund\FrontendUsersAccount;
use App\Models\User\UsersRechargeHistorie;
use App\Models\User\UsersWithdrawHistorie;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

trait PayTraits
{

    /**
     * @param FrontendUser $user              个人信息.
     * @param integer      $amount            金额.
     * @param string       $channel           通道.
     * @param string       $from              来源.
     * @param string       $payment_type_sign 支付方式种类.
     * @param integer      $payment_id        支付方式id.
     * @return boolean
     */
    public static function createRechargeOrder(FrontendUser $user, int $amount, string $channel, string $from, ?string $payment_type_sign, ?int $payment_id)
    {
        try {
            $data['user_id'] = $user->id;
            $data['user_name'] = $user->username;
            $data['is_tester'] = $user->is_tester;
            $data['top_agent'] = $user->top_agent;
            $data['channel'] = $channel;
            $data['payment_type_sign'] = $payment_type_sign;
            $data['payment_id'] = $payment_id;
            $data['amount'] = $amount;
            $data['company_order_num'] = BasePay::createRechargeOrderNum();
            $data['client_ip'] = real_ip();
            $data['deposit_mode'] = 0;
            $data['status'] = UsersRechargeHistorie::MODE_AUTOMATIC;
            $data['source'] = $from;
            $resule = UsersRechargeHistorie::create($data);
        } catch (Exception $e) {
            Log::channel('pay-recharge')->error('error-' . $e->getMessage() . '|' . $e->getLine() . '|' . $e->getFile());
            return false;
        }
        return $resule;
    }

    /**
     * @param FrontendUser $user  个人信息.
     * @param array        $datas 订单信息.
     * @return boolean
     * @throws Exception 异常.
     */
    public static function createWithdrawOrder(FrontendUser $user, array $datas)
    {
        DB::beginTransaction();
        try {
            $data['user_id'] = $user->id;
            $data['username'] = $user->username;
            $data['is_tester'] = $user->is_tester;
            $data['top_id'] = $user->top_id;
            $data['parent_id'] = $user->parent_id;
            $data['rid'] = $user->rid;
            $data['amount'] = $datas['amount'];
//            $data['card_id'] = $datas['card_id'];
            $data['card_number'] = $datas['card_number'];
            $data['card_username'] = $datas['card_username'];
            $data['bank_sign'] = $datas['bank_sign'];
            $data['request_time'] = time();
            $data['order_id'] = BasePay::createWithdrawOrderNum();
            $data['client_ip'] = real_ip();
            $data['status'] = UsersWithdrawHistorie::STATUS_AUDIT_WAIT;
            $data['source'] = $datas['from'] ?? 'web';

            $resule = UsersWithdrawHistorie::create($data);
                $params = [
                    'user_id' => $user->id,
                    'amount' => $datas['amount'],
                ];
                $account = FrontendUsersAccount::where('user_id', $user->id)->first();
                if ($account !== null) {
                    $resource = $account->operateAccount($params, 'withdraw_frozen');
                    if ($resource !== true) {
                        DB::rollBack();
                    }
                    DB::commit();
                } else {
                    DB::rollBack();
                    Log::channel('pay-withdraw')->info('用户account表不存在');
                }
        } catch (Exception $e) {
            DB::rollBack();
            Log::channel('pay-withdraw')->info('异常:' . $e->getMessage() . '|' . $e->getFile() . '|' . $e->getLine());
            return false;
        }
        return $resule;
    }

    /**
     * 设置提现单的状态等数据
     * @param array $datas 数据.
     * @return boolean
     */
    public static function setWithdrawOrder(array $datas)
    {
        $withdrawOrder = UsersWithdrawHistorie::where('id', '=', $datas['id'])->first();
        if ($withdrawOrder && $withdrawOrder->status !== $datas['status']) {
            return $withdrawOrder->update($datas);
        }
        return false;
    }
}
