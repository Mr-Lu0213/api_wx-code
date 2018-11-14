<?php
/**
 * Created by qizhao
 * Date: 2018/6/20
 * Time: 10:41
 */

namespace App\Model;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class WxCouponDetail extends Model
{
    protected $table = 'wx_coupon_detail';

    protected $primaryKey = 'ocd_id';

    const CREATED_AT = 'created_time';

    const UPDATED_AT = 'updated_time';

    protected $guarded = [];

    /**
     * 关联优惠券
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function coupon()
    {
        return $this->hasOne('App\Model\WxCoupon', 'coupon_id', 'coupon_id');
    }

    /**
     * 我的优惠券
     */
    public function myCoupon()
    {
        $list = $this->with(['coupon' => function ($query) {
            $query->select('coupon_id', 'name', 'type', 'target', 'amount', 'reach_amount');
        }])->where([
            'member_id' => get_member_id(),
            'status' => 1
        ])->select('ocd_id', 'coupon_id', 'start_time', 'end_time', 'status')->get();

        // 过滤已过期券
        $now = Carbon::now();
        foreach ($list as $k => $v) {
            if ($v->end_time < $now) {
                $v->status = 3;
                $v->save();
                unset($list[$k]);
                continue;
            }
        }
        return $list;
    }

    /**
     * 订单可用优惠券
     * @param $type
     * @param $service_id
     */
    public function orderCouponList($type, $service_id)
    {
        // 查询商品价格
        switch ($type) {
            case 1:
                $goodsModel = new ContentCourse();
                break;
            case 2:
                $goodsModel = new ContentExpert();
                break;
            case 3:
                $goodsModel = new WxWeekPlan();
                break;
            default:
                return [];
        }
        $goods = $goodsModel->find($service_id);

        $list = $this->myCoupon();
        foreach ($list as $k => $v) {
            if ($v->coupon->type == 1 && $v->coupon->reach_amount >= (int)$goods->price) {
                // 满减券判断是否达标
                unset($list[$k]);
            } else if ($v->coupon->target == 0 || $v->coupon->target == $type) {
                // 通用券和类型券
                continue;
            } else if ($v->coupon->target == 9) {
                // 指定券判断是否包含该商品
                $flag = 0;
                foreach ($v->coupon->goods as $item) {
                    if ($item->type == $type && $item->item_id == $service_id) {
                        $flag = 1;
                        break;
                    }
                }
                if (!$flag) {
                    unset($list[$k]);
                }
            } else {
                unset($list[$k]);
            }
        }
        return array_values($list->toArray());

    }

    /**
     * 检查是否可用
     * @param $cd_id
     * @param $origin_money
     * @return int
     */
    public function canUse($ocd_id, $origin_money)
    {
        $now = Carbon::now();
        $where = [
            'ocd_id' => $ocd_id,
            'status' => 1,
            'end_time' => ['>', $now],
            'start_time' => ['<', $now]
        ];
        $cd = $this->with('coupon')->where($where)->first();
        if (!$cd) {
            return 0;
        } else if ($cd->coupon->type == 2 ||
            ($cd->coupon->type == 1 && $cd->coupon->reach_amount <= $origin_money)
        ) {
            $cd->status = 2;
            $cd->use_time = $now;
            $cd->save();
            return $cd->coupon->amount;
        }
        return 0;
    }

}