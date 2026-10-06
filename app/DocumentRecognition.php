<?php
final class DocumentRecognition
{
    public static function analyze(string $absolutePath,string $mime,string $expectedName,string $expectedCpf): array
    {
        $cfg=(array)(app_config('document_recognition')??[]);
        $base=rtrim((string)($cfg['url']??getenv('FACE_SCANNER_URL')?:''),'/');
        $apiKey=(string)($cfg['api_key']??getenv('FACE_SCANNER_KEY')?:'');
        $enabled=(bool)($cfg['enabled']??($base!==''));
        $timeout=max(2,(int)($cfg['timeout_seconds']??12));

        $result=[
            'attempted'=>false,
            'status'=>'manual',
            'reason'=>'Análise automática não configurada.',
            'name_status'=>null,
            'name_score'=>null,
            'extracted_name'=>null,
            'extracted_cpf'=>null,
            'document_type'=>null,
            'raw'=>null,
        ];

        if(!$enabled || $base==='') return $result;
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){
            $result['reason']='Este formato será revisado manualmente. A análise automática atual processa imagens.';
            return $result;
        }
        if(!function_exists('curl_init')){
            $result['reason']='Servidor sem extensão cURL; documento encaminhado para revisão manual.';
            return $result;
        }

        $result['attempted']=true;
        $ch=curl_init($base.'/api/v1/document/analyze');
        $post=[
            'expected_name'=>$expectedName,
            'document_type'=>'auto',
            'front'=>new CURLFile($absolutePath,$mime,basename($absolutePath)),
        ];
        curl_setopt_array($ch,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$post,
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_TIMEOUT=>$timeout,
            CURLOPT_HTTPHEADER=>array_values(array_filter([
                'Accept: application/json',
                $apiKey!==''?'X-Face-Scanner-Key: '.$apiKey:null,
            ])),
        ]);
        $body=curl_exec($ch);
        $http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        $error=curl_error($ch);
        curl_close($ch);

        if($body===false || $http<200 || $http>=300){
            $result['status']='unavailable';
            $result['reason']=$error!==''?'Serviço de reconhecimento indisponível.':'Serviço de reconhecimento respondeu com erro HTTP '.$http.'.';
            return $result;
        }

        $payload=json_decode((string)$body,true);
        if(!is_array($payload)){
            $result['status']='unavailable';
            $result['reason']='Resposta inválida do serviço de reconhecimento.';
            return $result;
        }

        $fields=(array)($payload['fields']??[]);
        $nameValidation=(array)($payload['name_validation']??[]);
        $extractedCpf=preg_replace('/\D+/','',(string)($fields['cpf']??''));
        $expectedCpf=preg_replace('/\D+/','',$expectedCpf);
        $nameStatus=(string)($nameValidation['status']??'not_found');
        $cpfMatches=$extractedCpf!=='' && hash_equals($expectedCpf,$extractedCpf);
        $nameMatches=$nameStatus==='match';

        $result['name_status']=$nameStatus;
        $result['name_score']=isset($nameValidation['score'])?(float)$nameValidation['score']:null;
        $result['extracted_name']=$fields['name']??null;
        $result['extracted_cpf']=$extractedCpf?:null;
        $result['document_type']=$payload['detected_document_type']??null;
        $result['raw']=$payload;

        if($nameMatches && $cpfMatches){
            $result['status']='match';
            $result['reason']='Nome e CPF conferem com o cadastro.';
        }else{
            $result['status']='review';
            $parts=[];
            if(!$nameMatches) $parts[]='nome não confirmado automaticamente';
            if(!$cpfMatches) $parts[]=$extractedCpf===''?'CPF não localizado no documento':'CPF divergente do cadastro';
            $result['reason']=ucfirst(implode('; ',$parts)).'.';
        }
        return $result;
    }
}
