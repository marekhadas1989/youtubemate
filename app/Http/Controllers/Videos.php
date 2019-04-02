<?php

namespace App\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;

class Videos extends Controller
{
    public function getVideoInfo(){
        $response = array('as');
        return json_encode($response);
    }

    public function preparePlaylist(){
        $response = array('as');
        return json_encode($response);
    }

    private function va($p){

        echo '<pre>';
        print_r($p);
        echo '</pre>';

    }

    public function test(){
//https://www.youtube.com/playlist?list=PLttJ4RON7sleuL8wDpxbKHbSJ7BH4vvCk
        set_time_limit(0);

        $temp_dir = md5(time());
        $store_to = "C:\Users\wildsnaske\Documents\GitHub\youtubemate\public\downloads".DIRECTORY_SEPARATOR.$temp_dir;

        @mkdir($store_to);

        $save_to =
        // --ignore-errors              Continue on download errors, for example to skip unavailable videos in a playlist
        // --no-mark-watched            Do not mark videos watched (YouTube only)
        // --geo-bypass                 Bypass geographic restriction via faking X -Forwarded-For HTTP header
        // --yes-playlist                   Download the playlist, if the URL refers to a video and a playlist.

        $url = 'https://www.youtube.com/watch?v=kAQ-LnIEaXg&list=RDkAQ-LnIEaXg';
//$url = 'https://www.youtube.com/playlist?list=PLttJ4RON7sleuL8wDpxbKHbSJ7BH4vvCk';
        $command = '--geo-bypass';
       //$stat = exec('C:\Users\wildsnaske\Documents\GitHub\youtubemate\youtube-dl.exe --help',$output, $return_var);
//        $stat = exec('C:\Users\wildsnaske\Documents\GitHub\youtubemate\youtube-dl.exe -j --dump-json --flat-playlist -o "'.$store_to.'/%(title)s.%(ext)s" '.$url,$output, $return_var);








        /*
         * DOWNLOAD PLAYLIST
         */


        $stat = exec('C:\Users\wildsnaske\Documents\GitHub\youtubemate\youtube-dl.exe -i RDkAQ-LnIEaXg -o "'.$store_to.'/%(title)s.%(ext)s" '.$url,$output, $return_var);


    print_r($this->va($output));


        echo 'asd';
        return 'asd';
    }
}

