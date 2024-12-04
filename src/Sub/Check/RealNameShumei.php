<?php

namespace Ziyancs\Zytool\Sub\Check;
use Ziyancs\Zytool\Extends\RequestLibrary;

/**
 * 素美实名认证
 */
class RealNameShumei
{
    private static $SM_URL = 'https://mobile3elements.shumaidata.com/mobile/verify_real_name';

    /**
     * 身份验证三要素
     * @param $params
     * @return void
     */
    public static function checkRealName($params){
        $SM_APP_CODE=\Hyperf\Support\env('SM_APP_CODE','');
        $header  = [
            'Content-Type'  => 'application/x-www-form-urlencoded',
            'Authorization' => 'APPCODE ' . $SM_APP_CODE
        ];
        $paramsData     = [
            'idcard' => $params['card'],
            'mobile' => $params['mobile'],
            'name'   => $params['name']
        ];
        $res=RequestLibrary::requestPostResultJsonData(static::$SM_URL,$paramsData,$header,RequestLibrary::TYPE_FORM_PARAMS);
        return $res;
    }


}