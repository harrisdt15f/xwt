<?php
namespace App\Http\SingleActions\Frontend\User\AgentCenter;

use App\Http\Controllers\FrontendApi\FrontendApiMainController;
use App\Http\Requests\Frontend\UserAgentCenter\UserProfitsRequest;
use App\Models\Project;
use App\Models\User\FrontendUser;
use App\Models\User\UserProfits;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * 团队盈亏
 */
class UserProfitsAction
{
    /**
     * @var UserProfits
     */
    protected $model;

    /**
     * @var array
     */
    private $selectSum = [
        'sum(team_deposit) as team_deposit',
        'sum(team_withdrawal) as team_withdrawal',
        'sum(team_turnover) as team_turnover',
        'sum(team_prize) as team_prize',
        'sum(team_profit) as team_profit',
        'sum(team_commission) as team_commission',
        'sum(team_dividend) as team_dividend',
        'sum(team_daily_salary) as team_daily_salary',
        'sum(net_profit_total) as net_profit_total',
        'sum(team_net_profit_total + net_profit_total) as team_net_profit_total',
    ];

    /**
     * @var array
     */
    private $agentType;

    /**
     * UserAgentCenterAction constructor.
     * @param UserProfits $UserProfits UserProfits.
     */
    public function __construct(UserProfits $UserProfits)
    {
        $this->model = $UserProfits;
        $this->agentType = [
            FrontendUser::TYPE_TOP_AGENT,
            FrontendUser::TYPE_AGENT,
        ];
    }

    /**
     * 团队盈亏api
     * @param FrontendApiMainController $contll  Controller.
     * @param UserProfitsRequest        $request UserProfitsRequest.
     * @return JsonResponse
     */
    public function execute(FrontendApiMainController $contll, UserProfitsRequest $request): JsonResponse
    {
        $data = [];
        $summation = (object) [];

        $username = $request->input('username') ?? '';
        $dateTo = $request->input('date_to') ?? date('Y-m-d');
        $dateFrom = $request->input('date_from') ?? date('Y-m-01');
        $count = $request->input('count') ?? 15;

        $userInfo = $contll->currentAuth->user();

        if (in_array($userInfo->type, $this->agentType)) {
            $where = [['parent_id', $userInfo->id], ['date', '>=', $dateFrom], ['date', '<=', $dateTo]];
        } else {
            $where = [['user_id', $userInfo->id], ['date', '>=', $dateFrom], ['date', '<=', $dateTo]];
        }

        //区间合计 自己+下属的
        $data['sum'] = $this->getDataSum($summation, $userInfo, $username, $dateFrom, $dateTo, $where);

        //自己
        $selectRaw = array_merge(['user_id', 'username'], $this->selectSum);
        $data['self'] = $this->model->where([
            ['date', '>=', $dateFrom],
            ['date', '<=', $dateTo],
            ['user_id', $userInfo->id],
        ])->select(DB::raw(implode(',', $selectRaw)))
            ->first();

        if (in_array($userInfo->type, $this->agentType)) {
            //下级
            $data['child'] = $this->model
                ->where($where)
                ->select(DB::raw(implode(',', $selectRaw)))
                ->groupBy('user_id')
                ->paginate($count);
        }

        return $contll->msgOut(true, $data);
    }

    /**
     * 获取下级注册人数
     * @param integer $userId 用户id.
     * @param array   $date   时间搜索.
     * @return integer
     */
    private function getChildNum(int $userId, array $date = []): int
    {
        $number = FrontendUser::where([
            ['parent_id', '=', $userId],
        ]);
        if ($date) {
            $number->where([
                ['created_at', '>=', $date[0]],
                ['created_at', '<=', $date[1]],
            ]);
        }

        return $number->count();
    }

    /**
     * 获得时间区间内的投注人数
     * @param integer $userId 用户id.
     * @param array   $date   时间搜索.
     * @return integer
     */
    private function getBetNum(int $userId, array $date = []): int
    {
        return Project::where([
            ['parent_id', '=', $userId],
            ['created_at', '>=', $date[0]],
            ['created_at', '<=', $date[1]],
        ])
            ->groupBy('user_id')
            ->count();
    }

    /**
     * 区间合计 自己+下属的
     * @param  object       $summation 合计数据.
     * @param  FrontendUser $userInfo  FrontendUser.
     * @param  string       $username  用户名.
     * @param  string       $dateFrom  开始时间.
     * @param  string       $dateTo    结束时间.
     * @param  array        $where     搜索条件.
     * @return object
     */
    private function getDataSum(
        object $summation,
        FrontendUser $userInfo,
        string $username,
        string $dateFrom,
        string $dateTo,
        array$where
    ) {
        if (empty($username)) {
            if (in_array($userInfo->type, $this->agentType)) {
                $sumTeam = $this->model->where($where)->select(DB::raw(implode(',', $this->selectSum)))->first();
                $sumSelf = $this->model->where([
                    ['user_id', $userInfo->id],
                    ['date', '>=', $dateFrom],
                    ['date', '<=', $dateTo],
                ])->select(DB::raw(implode(',', $this->selectSum)))
                    ->first();
                $summation->team_deposit = $sumTeam->team_deposit + $sumSelf->team_deposit;
                $summation->team_withdrawal = $sumTeam->team_withdrawal + $sumSelf->team_withdrawal;
                $summation->team_turnover = $sumTeam->team_turnover + $sumSelf->team_turnover;
                $summation->team_prize = $sumTeam->team_prize + $sumSelf->team_prize;
                $summation->team_profit = $sumTeam->team_profit + $sumSelf->team_profit;
                $summation->team_commission = $sumTeam->team_commission + $sumSelf->team_commission;
                $summation->team_dividend = $sumTeam->team_dividend + $sumSelf->team_dividend;
                $summation->team_daily_salary = $sumTeam->team_daily_salary + $sumSelf->team_daily_salary;
                $summation->team_net_profit_total = $sumTeam->team_net_profit_total + $sumSelf->team_net_profit_total;
                //新注册人数(时间区间内注册的人数)、总下级人数
                //$sum->child_num = $this->getChildNum($userInfo->id);
                //$sum->new_child_num =  $this->getChildNum($userInfo->id, [$dateFrom, $dateTo]);
                //时间区间内投注人数
                //$sum->bet_child_num =  $this->getBetNum($userInfo->id, [$dateFrom, $dateTo]);
            } else {
                $summation = $this->model->where([
                    ['user_id', $userInfo->id],
                    ['date', '>=', $dateFrom],
                    ['date', '<=', $dateTo],
                ])->select(DB::raw(implode(',', $this->selectSum)))
                    ->first();
            }
            $data = $summation;
        } else {
            $data = $this->model->where(array_merge([['username', $username]], $where))
                ->select(DB::raw(implode(',', $this->selectSum)))
                ->first();
        }

        return $data;
    }
}
