<?php
/**
 * 公共方法
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/6/26
 * Time: 21:50
 */

/**
 * 返回结果集
 * @param $code 状态码
 * @param string $msg 提示信息
 * @param string $data 数据
 * @return array
 */
function returnMessage($code, $msg = '', $data = '')
{
    $result = [
        'code' => $code,
        'msg' => $msg,
        'data' => empty($data) ? (object)array() : (object)$data,
    ];
    return $result;
}

/**
 * 成功返回结果
 * @param $code 状态码
 * @param $msg 提示信息
 * @param string $data 数据
 * @return array
 */
function success($data = array(), $code = '200')
{
    $result = [
        'code' => $code,
        'msg' => '',
        'data' => empty($data) ? (object)array() : $data,
    ];
    return $result;
}

/**
 * 错误返回结果
 * @param $code 状态码
 * @param $msg 提示信息
 * @param string $data 数据
 * @return array
 */
function error($code = '200', $msg = '')
{
    $result = [
        'code' => $code,
        'msg' => $msg,
        'data' => empty($data) ? (object)array() : $data,
    ];
    return $result;
}

/**
 * 获取图片/视频上传key 并执行入库操作
 * @param $id  该对象id
 * @param  $model 模型
 * @param $field_id 表中的映射该id的字段名称
 * @param  $field_path 表中的key存入的字段名称
 * @param $key  图片名称
 * @return mixed
 */

function uploadKey($id,$model,$field_id,$field_path,$key){
    $model->$field_id = $id;
    $model->$field_path = env('IMG_DOMAIN').$key;
    $res = $model->save();
    if (!$res) return returnMessage('404', '图片上传失败');
    return returnMessage('200', '请求成功');
}

/**
 * 获取完整url
 * @param $key
 * @return string
 */
function getKey($key){
    $urlPath = env('IMG_DOMAIN').$key;
    return $urlPath;
}

/**
 *  获取图片/视频key
 * @param $id  该对象id
 * @param string $model 模型名称
 * @param string $field_id 表中的映射该id的字段名称
 * @return array
 */
function getUploadKey($id, $model = '', $field_id = '')
{
    $data = [];
    $res = $model->where($field_id, $id)->get();
    foreach ($res as $key => $val) {
        $data[] = [
            'id' => $val->id,
            'filePath' => $val->file_path,
            'sort' => $val->sort
        ];
    }
    return $data;
}


/**
 * 汇总方式 按月统计/按周统计
 * @param $startTime  开始时间(Unix 时间戳)
 * @param $endTime  结束时间(Unix 时间戳)
 * @param $type 分类 1.按周统计 2.按月统计
 * @return array
 */
function getSummer($startTime, $endTime, $type)
{
    $arr = array();
    $i = 0;
    while ($startTime <= $endTime) {
        if ($type == 1) {
            //按周
            $newTime = mktime(23, 59, 59, date('m', $startTime), date('d', $startTime) - date('w', $startTime) + 7, date('Y', $startTime));
        } elseif ($type == 2) {
            //按月
            $newTime = mktime(23, 59, 59, date('m', $startTime), date('t', $startTime), date('Y', $startTime));
        }
        $arr[$i]['start'] = date('Y-m-d', $startTime);

        if ($endTime <= $newTime)
            $arr[$i]['end'] = date('Y-m-d', $endTime);
        else
            $arr[$i]['end'] = date('Y-m-d', $newTime);

        $i++;
        $startTime = strtotime('+1 day', $newTime);
    }
    return $arr;
}

if (!function_exists('convertUnderline')) {
    /**
     * 下划线转驼峰
     */
    function convertUnderline($str)
    {
        $str = preg_replace_callback('/([-_]+([a-z]{1}))/i', function ($matches) {
            return strtoupper($matches[2]);
        }, $str);

    }
}


if (!function_exists('get_d_list')) {
    /**
     * 根据两日期，获取之间的日期列表
     * $start_time 开始日期 格式2016-07-01
     * $end_time 结束日期 格式2016-09-16
     */
    function get_d_list($start_time, $end_time)
    {
        $start_time = strtotime($start_time);
        $end_time = strtotime($end_time);
        $date_list = array();
        for ($start_time; $start_time <= $end_time; $start_time = $start_time + 3600 * 24) {
            $date_list[] = date('Y-m-d', $start_time);
        }
        return $date_list;
    }
}

/**
 * 获取指定日期段的日期
 * @param $startDate
 * @param $endDate
 * @return array
 */
function dateFromRange($startDate, $endDate)
{
    $sTimestamp = strtotime($startDate);
    $eTimestamp = strtotime($endDate);

    // 计算日期段内有多少天
    $days = ($eTimestamp-$sTimestamp)/86400+1;

    // 保存每天日期
    $date = array();

    for($i=0; $i<$days; $i++){
        $date[] = date('Y-m-d', $sTimestamp+(86400*$i));
    }
    return $date;
}

/**
 * 获取某日是星期几
 * @param $date
 * @return mixed
 */
function datetimeToWeek($date){
    //强制转换日期格式
    $date_str=date('Y-m-d',strtotime($date));
    //封装成数组
    $arr=explode("-", $date_str);
    //参数赋值
    //年
    $year=$arr[0];
    //月，输出2位整型，不够2位右对齐
    $month=sprintf('%02d',$arr[1]);
    //日，输出2位整型，不够2位右对齐
    $day=sprintf('%02d',$arr[2]);
    //时分秒默认赋值为0；
    $hour = $minute = $second = 0;
    //转换成时间戳
    $strap = mktime($hour,$minute,$second,$month,$day,$year);
    //获取数字型星期几
    $number_wk=date("w",$strap);
    //获取数字对应的星期
    return $number_wk + 1;
}


// 当前的毫秒时间戳
function msectime(){
    $arr = explode(' ', microtime());
    $tmp1 = $arr[0];
    $tmp2 = $arr[1];
    return (float)sprintf('%.0f', (floatval($tmp1) + floatval($tmp2)) * 1000);
}

// 10进制转62进制
function dec62($dec){
    $base = 62;
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $ret = '';
    for($t = floor(log10($dec) / log10($base)); $t >= 0; $t--){
        $a = floor($dec / pow($base, $t));
        $ret .= substr($chars, $a, 1);
        $dec -= $a * pow($base, $t);
    }
    return $ret;
}

// 随机字符
function rand_char(){
    $base = 62;
    $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return $chars[mt_rand(1, $base) - 1];
}

// 通过身份证获取生日
function getBirthdayByIdCard($idCard)
{
    $year = substr($idCard, 6, 4);
    $month = substr($idCard, 10, 2);
    $day = substr($idCard, 12, 2);

    return $year .'-'. $month .'-'. $day;
}

