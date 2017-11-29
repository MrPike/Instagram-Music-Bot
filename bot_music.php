<?php
require_once 'vendor/autoload.php';
require_once 'bot_config.php';

class bot_music
{
    private function postToARC($file){
        $http_method = "POST";
        $http_uri = "/v1/identify";
        $data_type = "audio";
        $signature_version = "1";
        $timestamp = time();
        $string_to_sign = $http_method . "\n" . $http_uri . "\n" . cfg::ARC_Key .
                          "\n" . $data_type . "\n" . $signature_version . "\n" . $timestamp;
        $signature = hash_hmac("sha1", $string_to_sign, cfg::ARC_Secret, true);
        $signature = base64_encode($signature);
        $filesize = filesize($file);
        $cfile = new CURLFile($file, cfg::Default_Audioformat);
        $postfields = array("sample" => $cfile, "sample_bytes" => $filesize, "access_key" => cfg::ARC_Key,
                            "data_type" => $data_type, "signature" => $signature,
                            "signature_version" => $signature_version, "timestamp" => $timestamp);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, cfg::ARC_Url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    private function GetArtistAndTitleFromJSON($jsonResult){
        if(!empty($jsonResult)){
            $j = json_decode($jsonResult);
            if($j->status->msg == "Success"){
                $title = explode('"', $j->metadata->music[0]->title)[0];
                $artist = explode('"', $j->metadata->music[0]->artists[0]->name)[0];
                return [$artist, $title];
            }
        }
        return null;
    }

    public function IdentifyMusicFile($filePath){
        // Should return an array [Artist, Title] on success. Null otherwise.
        $res = $this->postToARC($filePath);
        if(!empty($res)){
            return $this->GetArtistAndTitleFromJSON($res);
        }else{
            return [null,null];
        }

    }

}