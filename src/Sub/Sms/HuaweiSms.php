<?php
namespace Ziyanco\Zytool\Sub\Sms;

use GuzzleHttp\Client;

class HuaweiSms
{
    const POST_RUL = 'https://smsapi.cn-north-4.myhuaweicloud.com:443/sms/batchSendSms/v1';//阿里code
    const REDIS_KEY_SEND_PHONE = 'sms:send:phone:mobile_%s';  //redis缓存KEY


    public static function sendSms($mobile,$config): bool
    {

        $aliDigit = $config['HwyDigit'];
        $aliRedisUseTime =$config['HwyUseTime'];
        //生成数字
        $code = rand(pow(10, ($aliDigit - 1)), pow(10, $aliDigit) - 1);
        $APP_KEY = $config['HwyAppKey']; //APP_Key
        $APP_SECRET = $config['HwyAppSecret']; //APP_Secret
        $sender = $config['HwyAppForm'];//国内短信签名通道号
        $receiver = '+86' . $mobile; //短信接收人号码
        $TEMPLATE_ID = $config['HwyTemplateId'];//模板ID
        $TEMPLATE_PARAS = (string)$code;
        $statusCallback = '';
        $client = new Client();
        try {
            $response = $client->request('POST', HuaweiSms::POST_RUL, [
                'form_params' => [
                    'from' => $sender,
                    'to' => $receiver,
                    'templateId' => $TEMPLATE_ID,
                    'templateParas' => "['$TEMPLATE_PARAS']",
                    'statusCallback' => $statusCallback,

                ],
                'headers' => [
                    'Authorization' => 'WSSE realm="SDP",profile="UsernameToken",type="Appkey"',
                    'X-WSSE' => static::buildWsseHeader($APP_KEY, $APP_SECRET)
                ],
                'verify' => false
            ]);
            $resData = json_decode($response->getBody()->getContents(), true);

            if ($resData['code'] != '000000' || $resData['description'] != 'Success') {
                throw new \ErrorException('短信发送失败');
            }
            \Ziyanco\Library\Tool\RedisOptions::set(sprintf(HuaweiSms::REDIS_KEY_SEND_PHONE, $mobile), $code, $aliRedisUseTime);
            return true;
        } catch (\Throwable $e) {
            throw new \ErrorException('短信发送失败');
        }
        return true;
    }

    public static function checkSms($mobile, $code): bool
    {
        $redisKey = sprintf(HuaWeiSms::REDIS_KEY_SEND_PHONE, $mobile);
        $phoneCode = RedisOptions::get($redisKey);
        if ((string)$code != (string)$phoneCode) {
            return false;
        }
        return true;
    }

    private static function buildWsseHeader(string $appKey, string $appSecret)
    {
        $now = date('Y-m-d\TH:i:s\Z'); //Created
        $nonce = uniqid(); //Nonce
        $base64 = base64_encode(hash('sha256', ($nonce . $now . $appSecret))); //PasswordDigest
        return sprintf("UsernameToken Username=\"%s\",PasswordDigest=\"%s\",Nonce=\"%s\",Created=\"%s\"",
            $appKey, $base64, $nonce, $now);
    }
}