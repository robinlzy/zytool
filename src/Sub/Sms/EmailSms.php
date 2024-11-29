<?php
namespace Ziyancs\Zytool\Sub\Sms;

class EmailSms{

    public static  function doSendEmail($to = null, $title = null, $content = null, $filePath = null, $ssl = true)
    {
        $channel = new \Swoole\Coroutine\Channel();
        go(function () use ($channel,$to,$title,$content) {
            $mail = new \PHPMailer\PHPMailer\PHPMailer; //PHPMailer对象
            $mail->CharSet = 'UTF-8'; //设定邮件编码，默认ISO-8859-1，如果发中文此项必须设置，否则乱码
            $mail->IsSMTP(); // 设定使用SMTP服务
            $mail->SMTPDebug = 0; // 关闭SMTP调试功能
            $mail->SMTPAuth = true; // 启用 SMTP 验证功能
            $mail->SMTPSecure = 'ssl'; // 使用安全协议
            $mail->Host = env('STMP_HOST'); // SMTP 服务器
            $mail->Port = '465'; // SMTP服务器的端口号
            $mail->Username = env('STMP_HOST_USERNAME'); // SMTP服务器用户名
            $mail->Password = env('STMP_HOST_PASSWORD'); // SMTP服务器密码
            $mail->SetFrom(env('STMP_HOST_FORM'), env('STMP_HOST_NICKNAME')); // 邮箱，昵称
            $mail->Subject = $title;
            $mail->MsgHTML($content);
            $mail->AddAddress($to); // 收件人
            $result = $mail->Send();
            $channel->push($result);
        });
        return $channel->pop();
    }
}