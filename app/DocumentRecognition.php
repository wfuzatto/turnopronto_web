<?php
final class DocumentRecognition
{
    public static function analyze(string $filePath,string $mime,string $expectedName,string $expectedCpf,string $reference=''): array
    {
        $enabled=(bool)(app_config('document_recognition.enabled') ?? false);
        $base=rtrim(trim((string)(app_config('document_recognition.url') ?? '')),'/');
        $key=trim((string)(app_config('document_recognition.api_key') ?? ''));

        if(!$enabled || $base===''){
            return [
                'available'=>false,
                'decision'=>'manual',
                'provider'=>'face_scanner',
                'detail'=>'Validação automática não configurada; encaminhado para análise manual.',
            ];
        }
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){
            return [
                'available'=>false,
                'decision'=>'manual',
                'provider'=>'face_scanner',
                'detail'=>'Este formato será analisado manualmente. O OCR automático está habilitado para imagens JPG, PNG ou WEBP.',
            ];
        }
        if(!function_exists('curl_init')){
            return [
                'available'=>false,
                'decision'=>'manual',
                'provider'=>'face_scanner',
                'detail'=>'OCR automático indisponível no servidor web; encaminhado para análise manual.',
            ];
        }

        $endpoint=$base.'/api/v1/document/analyze';
        $post=[
            'expected_name'=>$expectedName,
            'reservation_id'=>$reference,
            'document_type'=>'auto',
            'front'=>new CURLFile($filePath,$mime,basename($filePath)),
        ];
        $headers=['Accept: application/json'];
        if($key!=='') $headers[]='X-Face-Scanner-Key: '.$key;

        $ch=curl_init($endpoint);
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$post,
            CURLOPT_HTTPHEADER=>$headers,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=5,
            CURLOPT_TIMEOUT=>max(8,(int)(app_config('document_recognition.timeout_seconds') ?? 25)),
            CURLOPT_FOLLOWLOCATION=>false,
        ]);
        $raw=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $error=curl_error($ch);
        curl_close($ch);

        if($raw===false || $status<200 || $status>=300){
            return [
                'available'=>false,
                'decision'=>'manual',
                'provider'=>'face_scanner',
                'detail'=>'O reconhecimento automático não respondeu'.($error!==''?' ('.$error.')':'.').' O documento permanece para análise manual.',
            ];
        }
        $payload=json_decode((string)$raw,true);
        if(!is_array($payload)){
            return [
                'available'=>false,
                'decision'=>'manual',
                'provider'=>'face_scanner',
                'detail'=>'Resposta inválida do reconhecimento automático; encaminhado para análise manual.',
            ];
        }
        return self::evaluateResponse($payload,$expectedCpf);
    }

    public static function evaluateResponse(array $payload,string $expectedCpf): array
    {
        $type=mb_strtolower(trim((string)($payload['detected_document_type']??'unknown')));
        $nameStatus=mb_strtolower(trim((string)($payload['name_validation']['status']??'not_found')));
        $extractedName=trim((string)($payload['name_validation']['extracted']??$payload['fields']['name']??''));
        $cpf=preg_replace('/\D+/','',(string)($payload['fields']['cpf']??''));
        $expected=preg_replace('/\D+/','',$expectedCpf);
        $typeOk=in_array($type,['cnh','rg','cin'],true);
        $cpfMatch=$cpf!=='' && hash_equals($expected,$cpf);
        $nameMatch=$nameStatus==='match';

        $parts=[];
        $parts[]=$typeOk?'Documento reconhecido como '.mb_strtoupper($type).'.':'Tipo de documento não reconhecido com segurança.';
        $parts[]=$nameMatch?'Nome confere com o cadastro.':($nameStatus==='review'?'Nome precisa de revisão.':'Nome não confere com o cadastro.');
        $parts[]=$cpf===''?'CPF não foi localizado no documento.':($cpfMatch?'CPF confere com o cadastro.':'CPF diverge do cadastro.');

        return [
            'available'=>true,
            'decision'=>($typeOk&&$nameMatch&&$cpfMatch)?'verified':'manual',
            'provider'=>'face_scanner',
            'detected_type'=>$type,
            'name_status'=>$nameStatus,
            'name_match'=>$nameMatch,
            'cpf_match'=>$cpfMatch,
            'extracted_name'=>$extractedName,
            'extracted_cpf'=>$cpf,
            'detail'=>implode(' ',$parts),
        ];
    }
}
