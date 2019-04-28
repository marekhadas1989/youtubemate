<?php

namespace App\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;
use ZipArchive;

set_time_limit(0);

class Cron extends Controller
{

    public function __construct()
    {
    }

    public function downloadThumbnails(){

        $vid = new \App\Videos();
        $videos = $vid->thumbnailssToDownload();

        foreach($videos as $v) {

            $command = 'youtube-dl --write-thumbnail --skip-download --get-filename -o "/var/www/html/youtubemate/public/downloads/thumbnails/%(title)s.%(ext)s" ' . $v->video_id;
            exec($command, $output, $return_var);
          //  exec('youtube-dl --write-thumbnail --skip-download -o "/var/www/html/youtubemate/public/downloads/thumbnails/%(title)s.%(ext)s" ' . $v->video_id, $output, $return_var);

            va($command);
            va($output);
            va($return_var);


        }
    }

    public function downloadVideos(){

        $vid = new \App\Videos();
        $videos = $vid->videosToDownload();

        foreach($videos as $v){

            $this->downloadThumbnails($v->video_id);
           // $vid->setStatus($v->id,'Downloading');


          //  exec($v->command,$output, $return_var);

        //    va($output);
        //    $vid->setStatus($v->id,'Downloaded');
        }

    }
}

