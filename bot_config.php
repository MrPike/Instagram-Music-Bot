<?php

interface cfg
{
    /**
     *  Set the directory (relative path to class file)
     *  where to save the downloads to.
     */
    const Download_Folder = 'videos/';

    const Audio_Folder = 'audio/';

    const Logging_Folder = 'logs/';

    /**
     * Specify the caching file (SQLite file). Extension should be .db
     */
    const Cache_DB_DSN = 'sqlite:InstaCache.db';

    /**
     *  Set the default audio quality (sample rate in kbits).
     *  Choose any integer value between 128 (low quality) and 320 (CD quality).
     *
     *  Note: Max. output quality depends on the video input file. Thus, the
     *  converted mp3 output file    may be worse, than expected from the value set.
     */
    const Default_Audioquality = 128;
    /**
     *  Set the default audio output filetype.
     *  Choose one of "mp3", "wav", "ogg", or "mp4".
     */
    const Default_Audioformat = "mp3";

    /**
     * Instagram Account Details
     */
    const IG_Username = "";
    const IG_Password = "";

    /**
     * Arcloud API Details
     */
    const ARC_Url = "";
    const ARC_Key = "";
    const ARC_Secret = "";

}