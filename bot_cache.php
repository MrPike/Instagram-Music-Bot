<?php
require_once 'vendor/autoload.php';
use \RedBeanPHP\R as R;


class bot_cache
{
    public function __construct($dbDSN)
    {
        try {
            R::setup($dbDSN);
            $r = R::dispense('postidtomusic');

            $r = R::dispense('processeditems');

        } catch (Exception $e) {
            throw new RuntimeException("Error initiating DB using provided DSN." . PHP_EOL);
        }
    }

    public function GetMusicResultFromCache($postId){
        try {
            $res = R::getAll('SELECT * FROM postidtomusic WHERE post_id = ? ', [$postId]);
            if(!empty($res)){
                return [$res[0]["artist_name"], $res[0]["track_title"]];
            }
        }
        catch (Exception $e){
            echo "Error in checking Cache for Historical Result." . PHP_EOL;
        }

        return null;
    }

    public function AddMusicResultToCache($postId, $artistName, $trackTitle)
    {
        if (!empty($postId)) {
            if (empty($this->GetMusicResultFromCache($postId))) {
                try {
                    $r = R::dispense('postidtomusic');
                    $r->postID = $postId;
                    $r->artistName = $artistName;
                    $r->trackTitle = $trackTitle;
                    R::store($r);
                    return true;
                } catch (Exception $e) {
                    throw new RuntimeException("Error adding Music match result to DB." . PHP_EOL);
                }
            }
        }
    }

    public function GetItemFromProcessedHistory($itemId){
        if (!empty($itemId)){
            if(!empty(R::getAll('SELECT * FROM processeditems WHERE item_id = ? ', [$itemId] )))
                return true;
        }
        return false;
    }

    public function AddItemToProcessedHistory($itemId){
        if(!empty($itemId)){
            if(!$this->GetItemFromProcessedHistory($itemId)) {
                try {
                    $r = R::dispense('processeditems');
                    $r->item_id = $itemId;
                    R::store($r);
                } catch (Exception $e) {
                    throw new RuntimeException("Error adding Processed Item to DB." . PHP_EOL);
                }
            }
        }

        return false;
    }
}
