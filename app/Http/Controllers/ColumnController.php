<?php
/**
 * 微课堂
 * Created by qizhao
 * Date: 2018/6/26
 * Time: 16:02
 */

namespace App\Http\Controllers;


use App\Model\WxBanner;
use App\Model\WxColumn;
use App\Model\WxColumnItem;

class ColumnController extends Controller
{
    /**
     * 首页
     */
    public function postIndex()
    {
        $result['banner_list'] = (new WxBanner())->homeList(2);  // banner

        $result['column_list'] = (new WxColumn())->getList();

        return response_success($result);

    }

    /**
     * 栏目详情
     */
    public function postDetail()
    {
        $column_id = $this->request->input('column_id', 0);

        $result['column_items'] = (new WxColumnItem())->getColumnItems($column_id);

        return response_success($result);
    }

}