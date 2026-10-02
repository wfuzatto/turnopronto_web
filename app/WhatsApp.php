<?php
final class WhatsApp
{
    public static function sendVerificationCode(string $phone, string $code): void
    {
        $driver=(string)(app_config('whatsapp.driver') ?? 'disabled');

        if($driver==='disabled' || $driver===''){
            throw new RuntimeException('Envio de WhatsApp ainda não configurado no servidor.');
        }

        if($driver==='debug'){
            if(!(bool)app_config('debug')){
                throw new RuntimeException('Driver debug de WhatsApp não pode ser usado em produção.');
            }
            error_log('[TurnoPronto WhatsApp DEBUG] '.$phone.' código '.$code);
            return;
        }

        if($driver==='webhook'){
            self::sendWebhook($phone,$code);
            return;
        }

        if($driver==='meta_cloud'){
            self::sendMetaCloud($phone,$code);
            return;
        }

        throw new RuntimeException('Driver de WhatsApp inválido.');
    }

    private static function sendWebhook(string $phone,string $code): void
    {
        $url=trim((string)(app_config('whatsapp.webhook_url') ?? ''));
        if($url==='') throw new RuntimeException('Webhook de WhatsApp não configurado.');
        $token=(string)(app_config('whatsapp.webhook_token') ?? '');
        $payload=json_encode([
            'to'=>$phone,
            'code'=>$code,
            'purpose'=>'turnopronto_phone_verification',
            'message'=>'Seu código TurnoPronto é '.$code.'. Ele expira em 10 minutos.',
        ],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        self::postJson($url,$payload,$token!==''?['Authorization: Bearer '.$token]:[]);
    }

    private static function sendMetaCloud(string $phone,string $code): void
    {
        $phoneNumberId=trim((string)(app_config('whatsapp.meta_phone_number_id') ?? ''));
        $token=trim((string)(app_config('whatsapp.meta_access_token') ?? ''));
        $version=trim((string)(app_config('whatsapp.meta_api_version') ?? 'v23.0'));
        $template=trim((string)(app_config('whatsapp.meta_template_name') ?? ''));
        $language=trim((string)(app_config('whatsapp.meta_template_language') ?? 'pt_BR'));
        if($phoneNumberId===''||$token==='') throw new RuntimeException('WhatsApp Cloud API não configurada.');

        $to=preg_replace('/\D+/','',$phone);
        $url='https://graph.facebook.com/'.rawurlencode($version).'/'.rawurlencode($phoneNumberId).'/messages';

        if($template!==''){
            $body=[
                'messaging_product'=>'whatsapp',
                'to'=>$to,
                'type'=>'template',
                'template'=>[
                    'name'=>$template,
                    'language'=>['code'=>$language],
                    'components'=>[
                        [
                            'type'=>'body',
                            'parameters'=>[
                                ['type'=>'text','text'=>$code],
                            ],
                        ],
                    ],
                ],
            ];
        }else{
            $body=[
                'messaging_product'=>'whatsapp',
                'recipient_type'=>'individual',
                'to'=>$to,
                'type'=>'text',
                'text'=>[
                    'preview_url'=>false,
                    'body'=>'Seu código TurnoPronto é '.$code.'. Ele expira em 10 minutos.',
                ],
            ];
        }

        self::postJson(
            $url,
            json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ['Authorization: Bearer '.$token]
        );
    }

    private static function postJson(string $url,string $payload,array $headers=[]): void
    {
        if(!function_exists('curl_init')) throw new RuntimeException('Extensão cURL não disponível no servidor.');
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$payload,
            CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$headers),
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>10,
            CURLOPT_TIMEOUT=>20,
        ]);
        $response=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $error=curl_error($ch);
        curl_close($ch);
        if($response===false || $status<200 || $status>=300){
            throw new RuntimeException('Falha ao enviar o código por WhatsApp'.($error!==''?': '.$error:'.'));
        }
    }
}
