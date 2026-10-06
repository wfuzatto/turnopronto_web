<?php
final class PostalCode
{
    public static function lookup(string $value): array
    {
        $cep=preg_replace('/\D+/','',$value);
        if(strlen($cep)!==8) throw new InvalidArgumentException('CEP inválido.');

        $providers=[
            'https://viacep.com.br/ws/'.$cep.'/json/',
            'https://brasilapi.com.br/api/cep/v1/'.$cep,
        ];
        foreach($providers as $url){
            try{
                $payload=self::getJson($url);
                if(!$payload) continue;

                if(str_contains($url,'viacep.com.br')){
                    if(!empty($payload['erro'])) continue;
                    return [
                        'postal_code'=>$cep,
                        'street'=>trim((string)($payload['logradouro']??'')),
                        'neighborhood'=>trim((string)($payload['bairro']??'')),
                        'city'=>trim((string)($payload['localidade']??'')),
                        'state'=>mb_strtoupper(trim((string)($payload['uf']??''))),
                        'provider'=>'ViaCEP',
                    ];
                }

                return [
                    'postal_code'=>$cep,
                    'street'=>trim((string)($payload['street']??'')),
                    'neighborhood'=>trim((string)($payload['neighborhood']??'')),
                    'city'=>trim((string)($payload['city']??'')),
                    'state'=>mb_strtoupper(trim((string)($payload['state']??''))),
                    'provider'=>'BrasilAPI',
                ];
            }catch(Throwable $e){
                continue;
            }
        }

        throw new RuntimeException('CEP não encontrado. Preencha o endereço manualmente.');
    }

    private static function getJson(string $url): ?array
    {
        if(!function_exists('curl_init')) return null;
        $ch=curl_init($url);
        curl_setopt_array($ch,[
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_HTTPHEADER=>['Accept: application/json','User-Agent: TurnoPronto/1.0'],
            CURLOPT_CONNECTTIMEOUT=>4,
            CURLOPT_TIMEOUT=>7,
            CURLOPT_FOLLOWLOCATION=>false,
        ]);
        $raw=curl_exec($ch);
        $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);
        curl_close($ch);
        if($raw===false || $status<200 || $status>=300) return null;
        $data=json_decode((string)$raw,true);
        return is_array($data)?$data:null;
    }
}
