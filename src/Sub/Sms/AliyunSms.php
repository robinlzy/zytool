<?php

namespace Ziyanco\Zytool\Sub\Sms;

use Ziyanco\Library\Extends\RequestLibrary;
use Ziyanco\Library\Tool\RedisOptions;

class AliyunSms
{
    const ALI_DIGIT = 6;  //短信位数
    const ALI_APP_CODE = '';//阿里code
    const ALI_TEMPLATE_ID = '';//阿里code
    const ALI_REDIS_USE_TIME = 60;  //redis缓存
    const POST_RUL = 'https://dfsns.market.alicloudapi.com/data/send_sms';//阿里code
    const REDIS_KEY_SEND_PHONE = 'sms:send:phone:mobile_%s';  //redis缓存KEY

    /*
     * 发送短信
     * @param $mobile
     * @param $code
     * @return void
     */
    public static function sendSms($mobile,$config): bool
    {
        //生成数字
        $code = rand(pow(10, (AliyunSms::ALI_DIGIT - 1)), pow(10, AliyunSms::ALI_DIGIT) - 1);
        $postData = [];
        $postData['content'] = 'code:' . $code;
        $postData['phone_number'] = $mobile;
        $postData['template_id'] = $config['ALI_TEMPLATE_ID'];
        $res = RequestLibrary::requestPostResultJsonData(AliyunSms::POST_RUL, $postData, [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Authorization' => 'APPCODE ' . $config['ALI_APP_CODE']
        ], RequestLibrary::TYPE_BUILD_QUERY);
        if (strtolower($res['status']) == 'ok') {
            RedisOptions::set(sprintf(AliyunSms::REDIS_KEY_SEND_PHONE, $mobile), $code, \Hyperf\Support\env('ALI_REDIS_USE_TIME', AliyunSms::ALI_REDIS_USE_TIME));
        } else {
            throw new \ErrorException($res['reason']);
        }
        return true;
    }

    /**
     * 检测短信
     * @param $mobile
     * @param $code
     * @return void
     */
    public static function checkSms($mobile, $code): bool
    {
        $redisKey = sprintf(AliyunSms::REDIS_KEY_SEND_PHONE, $mobile);
        $phoneCode = RedisOptions::get($redisKey);
        if ((string)$phoneCode !== (string)$code) {
            return false;
        }
        return true;
    }
}