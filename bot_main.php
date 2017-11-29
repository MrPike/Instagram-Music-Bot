<?php

require_once 'vendor/autoload.php';
require_once 'bot_config.php';
require_once 'bot_cache.php';
require_once 'bot_music.php';
use \RedBeanPHP\R as R;

class Ig_Music_Bot{

    private $ig = null;
    private $loop = null;
    private $push = null;
    private $logger = null;
    private $cache = null;
    private $arc = null;

    public function __construct($instantRun = true, $debug = false, $truncatedDebug = false)
    {
        try{
            $this->setup_config();
        }
        catch (Exception $e){

        }
        $this->ig = new \InstagramAPI\Instagram($debug, $truncatedDebug);
        $this->loop = \React\EventLoop\Factory::create();
        $this->cache = new bot_cache(cfg::Cache_DB_DSN);
        $this->arc = new bot_music();

        if ($debug == true){
            $this->logger = new \Monolog\Logger('rtc');
            $this->logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout', \Monolog\Logger::INFO));
        }

        if ($instantRun == true){
            $this->login_to_ig();
            $this->register_ig_event_listeners();
            $this->start_bot();
        }
    }

    private function setup_config(){
        if(empty(cfg::Download_Folder) or empty(cfg::Audio_Folder) or empty(cfg::Logging_Folder)
            or empty(cfg::Default_Audioquality) or empty(cfg::Default_Audioformat) or empty(cfg::IG_Username)
            or empty(cfg::IG_Password) or empty(cfg::ARC_Url) or empty(cfg::ARC_Key) or empty(cfg::ARC_Secret)
            or empty(cfg::Cache_DB_DSN))
        {
            throw new RuntimeException('Configuration issue. Please review bot_config.php');
        }

        if (!file_exists(cfg::Download_Folder)) {
            mkdir(cfg::Download_Folder, 0777, true);
        }

        if (!file_exists(cfg::Audio_Folder)) {
            mkdir(cfg::Audio_Folder, 0777, true);
        }

        if (!file_exists(cfg::Logging_Folder)) {
            mkdir(cfg::Logging_Folder, 0777, true);
        }
    }

    /**
     * Method responsible for logging into the Instagram account associated with this music bot.
     * @param bool $username Instagram username
     * @param bool $password Instagram password
     *
     * @throws RuntimeException In the event that the script is unable to login to Instagram.
     */
    public function login_to_ig($username = false, $password = false)
    {
        if ($username == false or $password == false){
            $username = cfg::IG_Username;
            $password = cfg::IG_Password;
        }

        try {
            $this->ig->login($username, $password);
            echo "Successfully in to the Instagram account of user ". $username . PHP_EOL;
        } catch (\Exception $e) {
            echo "FAILED in to the Instagram account of user ". $username . PHP_EOL;
            throw new RuntimeException("Unable to Login to Instagram. \n" . $e->getMessage().PHP_EOL);
        }

    }

    private function curl_httpstatus($url)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:11.0) Gecko Firefox/11.0");
        curl_setopt($ch, CURLOPT_REFERER, "http://www.instagram.com/p/");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        $str = curl_exec($ch);
        $int = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return intval($int);
    }

    private function curl_get_file($remote_file, $local_file)
    {
        $ch = curl_init($remote_file);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:11.0) Gecko Firefox/11.0");
        curl_setopt($ch, CURLOPT_REFERER,"http://www.instagram.com/p/");
        $fp = fopen($local_file, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_exec ($ch);
        curl_close ($ch);
        fclose($fp);
    }


    public function register_ig_event_listeners(){

        $this->push = new \InstagramAPI\Push($this->loop, $this->ig, $this->logger);

        $this->push->on('direct_v2_message', function (\InstagramAPI\Push\Notification $push) {

            $threadId = $push->getActionParam('id');
            $threadItemId = $push->getActionParam('x');
            $this->process_new_inbox_item($threadId, $threadItemId);
        });

        $this->push->on('error', function (Exception $e){
            printf('[!!!] Got fatal error from Realtime: %s%s', $e->getMessage(), PHP_EOL);
            $this->stop_bot();
        });
    }

    private function extract_audio($local_video_file, $audio_out_location)
    {
        $cmd = "ffmpeg -i \"$local_video_file\" -ar 44100 -ab 128k -ac 2 \"$audio_out_location\"";

        $Ffmpeg = exec($cmd);

        if(!file_exists($audio_out_location) !== FALSE) {
            throw new Exception("Something went wrong while converting the video into audio format, sorry!");
        }

    }

    private function download_and_extract($post_id)
    {
        $CURL_UA = "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:11.0) Gecko Firefox/11.0";
        $IG_BASE_URL = "http://www.instagram.com/p/";
        $url = $this->ig->media->getInfo($post_id)->getItems()[0]->getVideoVersions()[0]->getUrl();
        if ($this->curl_httpstatus($url) === 200) {
            $download_file = cfg::Download_Folder . $post_id . ".mp4";
            $audio_file = cfg::Audio_Folder . $post_id . "." . cfg::Default_Audioformat;
            try {
                $this->curl_get_file($url, $download_file);
                $this->extract_audio($download_file, $audio_file);
                return $audio_file;
            } catch (\Exception $e) {
                echo 'Something went wrong: ' . $e->getMessage() . "\n";
            }
        }

        return null;
    }

    private function send_result_to_user($threadId, $artist, $title){
        if (empty($artist) or empty($title)){
            $this->ig->direct->sendText(array('thread' => $threadId), "Unfortunately, I wasn't able to identify the music in this post.");
        }
        else{
            $this->ig->direct->sendText(array('thread' => $threadId), "The song was - ". $title . " by " . $artist);
        }
    }

    private function cleanup_item($itemId){
        $files = [cfg::Audio_Folder . $itemId . '.' . cfg::Default_Audioformat
        ,cfg::Download_Folder . $itemId . '.' . 'mp4'];

        foreach ($files as $f){
            if(file($f)){
                unlink($f);
            }
        }
    }

    private function get_item_from_thread($threadId, $threadItemId){
        $threadItems = $this->ig->direct->getThread($threadId)->getThread()->getItems();

        $threadItem = null;
        // TODO - Optimise this, go in reverse order?
        foreach ($threadItems as $tis) {
            if ($tis->getItemId() == $threadItemId) {
                $threadItem = $tis;
                break;
            }
        }
        return $threadItem;
    }

    private function get_post($threadId, $threadItemId){
        if(empty($threadId) or empty($threadItemId)){return null;}
        $threadItems = $this->ig->direct->getThread($threadId)->getThread()->getItems();
        if(!empty($threadItems)){
            foreach ($threadItems as $tis) {
                if ($tis->getItemId() == $threadItemId) {
                    if ($tis->getItemType() == 'media_share'){
                        return $tis->getMediaShare();
                    }
                }
            }
        }
        return null;
    }

    private function process_new_inbox_item ($threadId, $threadItemId)
    {
        // Only proceed if this is genuinely a new and unprocessed message
        if($this->cache->GetItemFromProcessedHistory($threadItemId)) { return null;}

        $post = $this->get_post($threadId, $threadItemId);

        if(empty($post)){
            // Return - Could not find post
            return;
        }

        if(!$post->getHasAudio()){
            // Media - Has no audio...
            return;
        }

        // We can consider this item processed for now (try once, or fail)
        $this->cache->AddItemToProcessedHistory($post->getId());

        // See if we've encountered this post before
        $music_result = $this->cache->GetMusicResultFromCache($post->getId());
        if(!empty($music_result)){
            $this->send_result_to_user($threadId, $music_result[0], $music_result[1]);
            return;
        }

        // If we reach here, then we may pressume that this is a new media item
        $audio_file_loc = cfg::Audio_Folder . $post->getId(). "." . cfg::Default_Audioformat;
        $audio_file_loc = $this->download_and_extract($post->getId());
        if(empty($audio_file_loc)){
            // Issue in extracting the audio from the video file
            return;
        }

        $result = $this->arc->IdentifyMusicFile($audio_file_loc);
        $this->cache->AddMusicResultToCache($post->getId(),$result[0], $result[1]);
        $this->send_result_to_user($threadId, $result[0], $result[1]);
        $this->cleanup_item($post->getId());
    }

    public function start_bot(){
        echo "Starting the main loop of the bot...".PHP_EOL;
        $this->push->start();
        $this->loop->run();
    }

    public function stop_bot(){
        echo "Stopping the main loop of the bot...".PHP_EOL;
        $this->push->stop();
        $this->loop->stop();
    }
}

new Ig_Music_Bot();