<?php

namespace App\Services\Util;

use App\Services\Util\YunTongXun\Rest as REST;
use App\Model\ClubErrorLog\ClubErrorLog;


class SmsService
{
    private $rest;
    public function __construct()
    {
        //配置相关参数
        $this->rest = new REST(config('yuntongxun.YTX')['SERVER_IP'], config('yuntongxun.YTX')['SERVER_PORT'], config('yuntongxun.YTX')['SOFT_VERSION']);
        $this->rest->setAccount(config('yuntongxun.YTX')['ACCOUNT_SID'], config('yuntongxun.YTX')['ACCOUNT_TOKEN']);
        $this->rest->setAppId(config('yuntongxun.YTX')['APPID']);
    }

    /**
     * 发送短信
     * @param $to 用户手机号
     * @param $datas 需要发送的数据
     * @param $tempId 短信模版ID
     * @return bool
     */
    public function sendSms($to,$datas,$tempId)
    {
        // 发送模板短信
        $log = "Sending TemplateSMS to {$to} ,模版ID: {$tempId},";
        $result = $this->rest->sendTemplateSMS($to, $datas, $tempId);

        unset($rest);
        if ($result == NULL) {
            $log .= "短信发送失败，错误信息返回NULL";
            echo $log;
            $this->addClubErrorLog($log);
            return false;
        }

        if ($result->statusCode != 0) {
            $log .= "短信发送失败，错误code:{$result->statusCode},错误msg:{$result->statusMsg}";
            echo $log;
            $this->addClubErrorLog($log);
            return false;
        } else {
            $log .= "短信发送成功";
            $this->addClubErrorLog($log);
            return true;
        }

    }

    /**
     * 发送短信(群发)
     * @param $to 用户手机号
     * @param $datas 需要发送的数据
     * @param $tempId 短信模版ID
     */
    public function sendGroupSms($to,$datas,$tempId)
    {
        // 发送模板短信
        $log = "Sending TemplateSMS to {$to} ,模版ID: {$tempId},";
        $result = $this->rest->sendTemplateSMS($to, $datas, $tempId);

        unset($rest);
        if ($result == NULL) {
            $log .= "短信发送失败，错误信息返回NULL";
            $this->addClubErrorLog($log);
        }

        if ($result->statusCode != 0) {
            $log .= "短信发送失败，错误code:{$result->statusCode},错误msg:{$result->statusMsg}";
            $this->addClubErrorLog($log);
        }
    }

    /**
     * 添加俱乐部错误日志
     * @param $errorContent
     * @param string $channel
     */
    private function addClubErrorLog($errorContent,$channel = 'sms')
    {
        $clubErrorLog = new ClubErrorLog();
        $clubErrorLog->error_channel = $channel;
        $clubErrorLog->error_content = $errorContent;

        $clubErrorLog->save();
    }

}