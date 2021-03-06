<?php
namespace ParanoidSqd\WDT;

use WDT\Core\WdtException;

/**
 * Class WdtClient
 *
 */
class WdtClient
{
    /**
     * 构造函数
     *
     *
     * @param string $sid 购买ERP时由旺店通分配给ERP的用户。对接的技术如果需要此参数，请从ERP用户处获取（即卖家）
     * @param string $appkey 由商务（销售）人员通过申请获取，使用接口必须要此参数，请联系旺店通的商务人员申请分配
     * @param string $appsecret 接口密钥
     * @param string $baseUrl 接口地址, 例如 http://x.x.x.x/openapi2
     * @throws WdtException
     */
    public function __construct($sid, $appkey, $appsecret, $baseUrl)
    {
        $sid = trim($sid);
        $appkey = trim($appkey);
        $appsecret = trim($appsecret);
        $baseUrl = trim($baseUrl);

        if (empty($sid)) {
            throw new WdtException("sid is empty");
        }
        if (empty($appkey)) {
            throw new WdtException("appkey is empty");
        }
        if (empty($appsecret)) {
            throw new WdtException("appsecret is empty");
        }
        if (empty($baseUrl)) {
            throw new WdtException("baseUrl is empty");
        }
        $this->sid = $sid;
        $this->appkey = $appkey;
        $this->appsecret = $appsecret;
        $this->baseUrl = $baseUrl;
    }

    //打包参数
    function packData(&$req)
    {
        ksort($req);
        $arr = array();
        foreach($req as $key => $val)
        {
            if($key == 'sign') continue;
            if(count($arr))
                $arr[] = ';';
            $arr[] = sprintf("%02d", iconv_strlen($key, 'UTF-8'));
            $arr[] = '-';
            $arr[] = $key;
            $arr[] = ':';
            $arr[] = sprintf("%04d", iconv_strlen($val, 'UTF-8'));
            $arr[] = '-';
            $arr[] = $val;
        }
        return implode('', $arr);
    }

    //加密生成sign
    function makeSign(&$reqBody)
    {
        $sign = md5($this->packData($reqBody) . $this->appsecret);
        $reqBody['sign'] = $sign;
    }

    //发送请求
    function wdtOpenApi($requestBody, $url)
    {
        $requestBody['sid'] = $this->sid;
        $requestBody['appkey'] = $this->appkey;
        $requestBody['timestamp'] = time();

        $this->makeSign($requestBody);
        $postdata = http_build_query($requestBody);
        $length   = strlen($postdata);
        $cl       = curl_init($this->baseUrl . $url);
        curl_setopt($cl, CURLOPT_POST, true);
        curl_setopt($cl,CURLOPT_HTTP_VERSION,CURL_HTTP_VERSION_1_1);
        curl_setopt($cl,CURLOPT_HTTPHEADER, array("Content-Type: application/x-www-form-urlencoded","Content-length: " . $length));
        curl_setopt($cl,CURLOPT_POSTFIELDS,$postdata);
        curl_setopt($cl,CURLOPT_RETURNTRANSFER,true);
        $content = curl_exec($cl);

        if (curl_errno($cl))
        {
            echo "Error: " . curl_error($cl);
        }
        curl_close($cl);
        $json = json_decode($content);
        if(!$json)
        {
            var_dump($content);
            return NULL;
        }
        return $json;
    }

    function tradePush($trade_list, $shopNo, $switch=0)
    {
        $reqBody = array
        (
            'shop_no'    => $shopNo,
            'switch'     => $switch, // 0为非严格模式  1为严格模式
            'trade_list' => json_encode($trade_list, JSON_UNESCAPED_UNICODE)
        );
        $res = $this->wdtOpenApi($reqBody, '/openapi2/trade_push.php');

        return $res;
    }

    function logisticsSyncQuery($shopNo, $limit=100)
    {
        $reqBody = array
        (
            'shop_no'    => $shopNo,
            'limit'     => $limit, // 最多返回条数(超过100条，默认返回100条)
        );
        $res = $this->wdtOpenApi($reqBody, '/openapi2/logistics_sync_query.php');

        return $res;
    }

    /**
     * @param $logisticsList
     * “logistics_list”:
     * [
     * {
     * "rec_id": "1",
     * "status": 0,
     * "message": "同步成功"
     * },
     * {
     * "rec_id": "2",
     * "status": 1,
     * "message": "同步失败:物流单号不格式错误"
     * }
     * ]
     * @return mixed|null
     */
    function logisticsSyncAck($logisticsList)
    {
        $res = $this->wdtOpenApi($logisticsList, '/openapi2/logistics_sync_ack.php');

        return $res;
    }

    function stockQuery($warehouseNo, $specNo, $barCode, $startTime, $endTime, $pageSize, $pageNo)
    {
        $reqBody = array
        (
            'warehouse_no'    => $warehouseNo,
            'spec_no'    => $specNo,
            'barcode'    => $barCode,
            'start_time'    => $startTime,
            'end_time'    => $endTime,
            'page_size'    => $pageSize,
            'page_no'    => $pageNo,
        );
        $res = $this->wdtOpenApi($reqBody, '/openapi2/stock_query.php');

        return $res;
    }

    /**
     * @param $refundList
     *
     * "api_refund_list":
     *     [
     *         {
     *         "tid": "test00053120009-3",
     *         "shop_no":"test",
     *         "platform_id":127
     *         "refund_no": 6,
     *         "type": "1",
     *         "status": "success",
     *         "refund_fee": "",
     *         "alipay_no": "mytest",
     *         "buyer_nick": "",
     *         "refund_time": "1212121",
     *         "reason": "测试者",
     *         "desc": "北京",
     *         "refund_version": "北京市",
     *         "order_list":
     *             [
     *                 {
     *                 "oid": "test0005-01-03",
     *                 "num": 2
     *                 }
     *             ]
     *         }
     *     ]
     * @return mixed|null
     */
    function refund($refundList)
    {
        $reqBody = array(
            'api_refund_list' => json_encode($refundList, JSON_UNESCAPED_UNICODE)
        );
        $res = $this->wdtOpenApi($reqBody, '/openapi2/sales_refund_push.php');
        return $res;
    }
}