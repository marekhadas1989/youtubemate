<?php

namespace App\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;
use ZipArchive;
use Illuminate\Support\Facades\DB;
set_time_limit(0);

class Videos extends Controller
{
    private $platform = 'Linux';

    private $server_dir = "";
    private $youtube_dl = "";
    private $ffmpeg     = "";



    public function __construct()
    {

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {

            $this->platform     = 'Windows';
            $this->server_dir   = 'C:\Users\wildsnaske\Documents\GitHub\youtubemate\public\downloads';
            $this->youtube_dl   = "C:\Users\wildsnaske\Documents\GitHub\youtubemate\youtube-dl.exe";
            $this->ffmpeg       = "C:\Users\wildsnaske\Documents\GitHub\youtubemate\ffmpeg.exe";

        } else {

            $this->platform = 'Linux';
            $this->server_dir   = '/var/www/html/youtubemate/public/downloads';
            $this->youtube_dl   = "youtube-dl";
            $this->ffmpeg       = "ffmpeg";

        }
    }

    private function va($p){

        echo '<pre>';
        print_r($p);
        echo '</pre>';

    }

    /*
     * Convert audio into valid mp3 format with VBR
     */
    private function convertAudio($file,$filename){

        $new_filename = basename($filename).'_vbr.mp3';

        $ffmpeg_merge = $this->ffmpeg.' -y -i '.$file.$filename.' -vn -ar 44100 -ac 2 -q:a 1 -codec:a libmp3lame '.$file.$new_filename.' 2>&1';

        exec($ffmpeg_merge,$output, $return_var);

        //remove old file
        @unlink($file.$filename);

    }
    private function mergeVideo($audio_stream,$video_stream,$playlist = false){

        if(!$playlist){
            $playlist = '';
        }

        $video_name = basename($video_stream);
        $audio_name = basename($audio_stream);

        $video_ext  = pathinfo($video_name, PATHINFO_EXTENSION);
        $audio_ext  = pathinfo($audio_name, PATHINFO_EXTENSION);

        $fname      = pathinfo($video_stream, PATHINFO_FILENAME).'_'.time();

        if($video_ext == 'webm' || $audio_ext == 'webm'){
            $ext = 'webm';
        }else{
            $ext = $video_ext;
        }

        $save_to = $this->server_dir.DIRECTORY_SEPARATOR.$playlist.$fname.'.'.$ext;

        $ffmpeg_merge = $this->ffmpeg.' -i "'.$this->server_dir.DIRECTORY_SEPARATOR.$playlist.$video_stream.'" -i "'.$this->server_dir.DIRECTORY_SEPARATOR.$playlist.$audio_stream.'" -c copy "'.$save_to.'" 2>&1';

        exec($ffmpeg_merge,$output, $return_var);


        if(is_readable($save_to)){

            @unlink($this->server_dir.DIRECTORY_SEPARATOR.$playlist.$video_stream);
            @unlink($this->server_dir.DIRECTORY_SEPARATOR.$playlist.$audio_stream);

            return $fname.'.'.$ext;

        }else{
            return false;
        }
    }
    /*
     * Process playlist based on selected URL
     */
    private function getPlaylistInfo($url,$playlist_id){

        $code       = file_get_contents($url);

        libxml_use_internal_errors(true);

        $dochtml    = new \DOMDocument();
        $code       = mb_convert_encoding($code, 'HTML-ENTITIES', "UTF-8");

        $dochtml->loadHTML($code);

        $xml = simplexml_import_dom($dochtml);
        $element_video = $xml->xpath("//*[@id='player-playlist']/div/div/div/ol/li/a");

        $available = array();
        $deleted = array();

        foreach($element_video as $el){

            $thumbnail      = (array)$el->span->span->span->img->attributes()->{'data-thumb'};
            $thumbnail_src  = (array)$el->span->span->span->img->attributes()->{'src'};
            $tt             = (array)$el->div->h4;
            $title          = trim($tt[0]);

            $video_id       = (array)($el->attributes()->href);
            $video_id       = str_replace('/watch?v=','',$video_id[0]);
            $video_id       = explode("&",$video_id)[0];

            if($title == 'Deleted video'){

                $deleted[]= array(
                    "url"       =>  "https://www.youtube.com/watch?v=".$video_id,
                    'video_id'  =>  $video_id,
                    'thumbnail' =>  !empty($thumbnail[0])?$thumbnail[0]:$thumbnail_src[0],
                    'title'     =>  $title
                );

            }else{

                $available[] = array(
                    "url"       =>  "https://www.youtube.com/watch?v=".$video_id,
                    'video_id'  =>  $video_id,
                    'thumbnail' =>  !empty($thumbnail[0])?$thumbnail[0]:$thumbnail_src[0],
                    'title'     =>  $title,
                    'formats'   =>  $this->parseAvailableFormatsJSON("https://www.youtube.com/watch?v=".$video_id)
                );

            }
        };

        return array(
            'playlist_id'   => $playlist_id,
            'deleted'       => $deleted,
            'available'     => $available
        );

    }

    /*
     * Parse available video/audio formats based on specified url
     */
    private function parseAvailableFormatsJSON($video){

        $command = $this->youtube_dl.' -j '.$video;


        //format code  extension  resolution note
        exec($command,$output, $return_var);

        if(empty($output[0])){
            return false;
        }
        $response = json_decode($output[0]);

        $processed_videos   = array();
        $processed_audio    = array();

        foreach($response->formats as $f){

            if(!empty($f->height)){
                //video

                $processed_videos[$f->format_id] = array(
                    'format_id'     =>  $f->format_id,
                    'file_size'     =>  isset($f->filesize)?round($f->filesize/1024/1024,3):'Unknown',//MB
                    'resolution'    =>  $f->width.'x'.$f->height.' ('.$f->format_note.')',//'KB/s'
                    'bitrate'       =>  isset($f->tbr)?$f->tbr:'unknown',
                    'extension'     =>  $f->ext,
                    'codec'         =>  $f->vcodec,
                );

            }else{
                //audio

                $processed_audio[$f->format_id] = array(
                    'format_id'     =>  $f->format_id,
                    'file_size'     =>  isset($f->filesize)?round($f->filesize/1024/1024,3):'Unknown',//MB
                    'bitrate'       =>  isset($f->tbr)?$f->tbr:'unknown',//'KB/s'
                    'extension'     =>  $f->ext,
                    'codec'         =>  $f->acodec,
                );

            }
        };

        usort($processed_audio, function ($item1, $item2) {
            return $item1['file_size'] <=> $item2['file_size'];
        });

        usort($processed_videos, function ($item1, $item2) {
            return $item1['file_size'] <=> $item2['file_size'];
        });

        return array(
            'audio'  =>  $processed_audio,
            'video'  =>  $processed_videos
        );

    }

    private function getVideoInfo($video_url){

        $video_info     =   $this->parseAvailableFormatsJSON($video_url);
        $title_thumb    =   $this->getVideoTitleAndThumbnail($video_url);

        $available = array();

        $available[] = array(
            'video_id'  =>  $video_url,
            'thumbnail' =>  $title_thumb['thumbnail'],
            'title'     =>  $title_thumb['title'],
            'formats'   =>  $video_info
        );

        return array(
            'deleted'   => array(),
            'available' => $available
        );

        return $video_info;
    }

    public function displayVideosInfo(Request $req){

        $video_id       = $req->input('video_id');
        $playlist_id    = $req->input('playlist_id');
        $url            = $req->input('url');


        if($playlist_id == 'false'){
            $response = $this->getVideoInfo($video_id);

        }else{
           // $response = $this->getPlaylistInfo($url,$playlist_id);

            $response = unserialize('a:3:{s:11:"playlist_id";s:41:"RDGMEMYH9CUrFO7CfLJpaD7UR85wVMPVxCwgO98Ek";s:7:"deleted";a:0:{}s:9:"available";a:26:{i:0;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=LM0ee-BA9Z0";s:8:"video_id";s:11:"LM0ee-BA9Z0";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/LM0ee-BA9Z0/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLBq06L8r9-RyylLhGiHZEku9cQiPA";s:5:"title";s:22:"ATB - Beautiful Worlds";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.816;s:7:"bitrate";d:52.632;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:2.418;s:7:"bitrate";d:70.29;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:4.583;s:7:"bitrate";d:135.296;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:4.739;s:7:"bitrate";d:130.585;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:4.823;s:7:"bitrate";d:139.146;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:15:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:2.624;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:135.531;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"394";s:9:"file_size";d:2.735;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:92.081;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:3.614;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:141.845;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:5.698;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:369.261;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:4;a:6:{s:9:"format_id";s:3:"395";s:9:"file_size";d:5.805;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:208.109;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:5;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:5.937;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:223.619;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"396";s:9:"file_size";d:11.143;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:370.043;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:7;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:11.539;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:408.479;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:12.367;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:685.919;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:9;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:20.425;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:1146.236;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:10;a:6:{s:9:"format_id";s:3:"397";s:9:"file_size";d:20.753;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:691.768;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:11;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:20.843;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:759.063;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:23.602;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:13;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:37.584;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:2224.029;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:14;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:43.747;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1587.303;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:1;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=P4QoBoccHEk";s:8:"video_id";s:11:"P4QoBoccHEk";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/P4QoBoccHEk/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLBMhLcRbSgPoSrutq7etFqFPpwijg";s:5:"title";s:30:"GAB - This Moment (Radio Edit)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:0.902;s:7:"bitrate";d:53.909;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.189;s:7:"bitrate";d:71.08;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.174;s:7:"bitrate";d:127.805;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.325;s:7:"bitrate";d:130.349;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.338;s:7:"bitrate";d:139.181;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.221;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:30.37;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.482;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:68.344;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.549;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:114.662;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:0.756;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:128.437;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:4;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.809;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:130.357;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:0.9;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:175.94;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:6;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:1.239;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:214.086;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.456;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:241.298;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:1.476;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:236.013;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:2.878;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:663.973;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:3.084;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:573.092;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:4.131;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:6.394;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1480.741;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:2;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=flTfjJdPd98";s:8:"video_id";s:11:"flTfjJdPd98";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/flTfjJdPd98/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLCRM5RVfO2Fq-ukQ86jxXeooV-47w";s:5:"title";s:27:"Robin Knaak - Believe In Me";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.322;s:7:"bitrate";d:63.935;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.737;s:7:"bitrate";d:82.767;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.957;s:7:"bitrate";d:131.537;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.047;s:7:"bitrate";d:128.071;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.394;s:7:"bitrate";d:154.82;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.365;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:29.379;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.563;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:46.532;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.774;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:74.884;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.116;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:97.687;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:4;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.202;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:68.14;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:1.899;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:170.17;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:6;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.131;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:121.87;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:7;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.335;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:302.754;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:8;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:3.346;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:194.76;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:5.924;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:562.514;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:5.936;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:388.197;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:8.152;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:12.005;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:993.801;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:3;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=seON9J5DDDs";s:8:"video_id";s:11:"seON9J5DDDs";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/seON9J5DDDs/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLD4viFbYzc_ppfNpcy4Cm8KEWejmg";s:5:"title";s:50:"Dallerium - Lost In Moment (ChillYourMind Release)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.088;s:7:"bitrate";d:50.81;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.439;s:7:"bitrate";d:67.411;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.796;s:7:"bitrate";d:127.931;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.811;s:7:"bitrate";d:131.449;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.865;s:7:"bitrate";d:135.773;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.4;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:31.383;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.866;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:75.856;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.946;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:59.862;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.98;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:68.123;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.625;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:152.6;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.7;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:118.381;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.502;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:237.262;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.661;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:232.163;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:4.12;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:410.803;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:5.072;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:396.149;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.666;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:12.089;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1021.104;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:14.088;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1481.567;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:4;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=WfLwKu-nhas";s:8:"video_id";s:11:"WfLwKu-nhas";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/WfLwKu-nhas/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLDB648p61_Sl4us0P16S_dGiqu0Eg";s:5:"title";s:23:"Nora Van Elken - I Know";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.279;s:7:"bitrate";d:57.504;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.705;s:7:"bitrate";d:78.041;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:3.086;s:7:"bitrate";d:132.746;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.107;s:7:"bitrate";d:127.956;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.369;s:7:"bitrate";d:151.549;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.371;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:24.663;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.728;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:51.519;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:2;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.768;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:57.589;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.826;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:58.914;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.475;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:108.833;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.808;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:107.715;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.12;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:152.148;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.592;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:175.986;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:2.928;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:233.017;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:3.66;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:287.773;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:4.282;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:346.308;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:11;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:6.329;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:511.675;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.313;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}}}}i:5;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=hQyBwaOVx9I";s:8:"video_id";s:11:"hQyBwaOVx9I";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/hQyBwaOVx9I/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLBLqZpq_OVTNLcP-VEhWQslZlODzw";s:5:"title";s:31:"DJ Vianu - Beloved (GeoM Remix)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.995;s:7:"bitrate";d:54.042;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:2.649;s:7:"bitrate";d:70.752;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:5.164;s:7:"bitrate";d:130.592;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:5.183;s:7:"bitrate";d:146.952;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:5.272;s:7:"bitrate";d:138.029;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.546;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:33.477;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:1.071;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:57.06;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:1.361;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:62.742;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.89;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:108.149;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:4;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:2.215;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:75.564;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.441;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:131.825;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:6;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.928;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:213.589;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:4.177;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:138.409;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:4.277;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:134.785;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:8.802;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:550.672;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:9.648;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:334.019;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:10.427;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:21.121;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:727.908;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:6;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=E26rOoPjWWk";s:8:"video_id";s:11:"E26rOoPjWWk";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/E26rOoPjWWk/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLDl3JJBMuhUadWdkFDjKoP7uI-qVQ";s:5:"title";s:39:"GAB - One Night (ChillYourMind Release)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.311;s:7:"bitrate";d:52.895;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.732;s:7:"bitrate";d:69.417;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:3.3;s:7:"bitrate";d:133.299;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.349;s:7:"bitrate";d:127.969;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.415;s:7:"bitrate";d:136.784;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.458;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:27.993;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.991;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:67.075;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:1.158;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:91.088;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.731;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:118.17;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.738;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:127.749;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.631;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:201.548;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:6;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:3.286;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:207.808;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:3.493;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:211.62;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.992;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:372.221;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:5.688;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:450.41;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:8.672;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:9.585;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:703.648;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:10.993;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1303.313;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:7;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=PVxCwgO98Ek";s:8:"video_id";s:11:"PVxCwgO98Ek";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/PVxCwgO98Ek/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLBRwNw1zhaHB2vzs2wCHxgEsM4chw";s:5:"title";s:38:"Imad - Nightcry (Official Lyric Video)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.214;s:7:"bitrate";d:63.552;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.602;s:7:"bitrate";d:82.07;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.803;s:7:"bitrate";d:131.084;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.88;s:7:"bitrate";d:127.907;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.163;s:7:"bitrate";d:155.001;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:11:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.835;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:70.613;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:1.939;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:101.454;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:2;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:2.209;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:163.347;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:2.257;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:177.102;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:5.05;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:399.665;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:5.407;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:404.991;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:8.442;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:628.092;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:9.161;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:703.256;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:9.711;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:9;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:14.224;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1190.459;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:10;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:17.302;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1208.753;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:8;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=nb1y4AWYpvI";s:8:"video_id";s:11:"nb1y4AWYpvI";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/nb1y4AWYpvI/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLAlyLPY-8cpk9QWB0fm5NJZoJ0Pzg";s:5:"title";s:44:"Ku De Ta - Move Ya Body (feat. Nikki Ambers)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:0.995;s:7:"bitrate";d:54.438;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.307;s:7:"bitrate";d:71.709;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.514;s:7:"bitrate";d:127.973;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.548;s:7:"bitrate";d:134.031;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.583;s:7:"bitrate";d:140.161;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.364;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:79.421;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.745;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:84.409;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:2;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.841;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:157.065;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.958;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:198.275;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.579;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:361.805;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.912;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:368.48;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.351;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:666.412;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:3.156;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:612.084;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.61;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1155.724;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:4.569;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1106.968;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:5.651;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:2058.257;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:6.864;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:8.521;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1848.31;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:9;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=COqBLsYNh3g";s:8:"video_id";s:11:"COqBLsYNh3g";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/COqBLsYNh3g/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLD_EHziG5UR9L3vrBSibwUvcElATA";s:5:"title";s:24:"Nora Van Elken - Highway";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.199;s:7:"bitrate";d:52.768;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.593;s:7:"bitrate";d:69.619;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.955;s:7:"bitrate";d:132.704;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.007;s:7:"bitrate";d:127.991;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.192;s:7:"bitrate";d:138.98;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.38;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:43.426;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.823;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:87.932;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.924;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:65.988;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.352;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:125.621;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.517;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:173.608;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.286;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:270.65;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:6;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.613;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:308.787;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.719;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:428.247;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.509;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:439.482;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:7.491;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1012.333;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.864;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:8.406;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1094.724;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:8.983;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1923.369;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:10;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=z9HaRI5yWzc";s:8:"video_id";s:11:"z9HaRI5yWzc";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/z9HaRI5yWzc/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLBgosmHzPpA088uB-fXcMdbSa54vg";s:5:"title";s:28:"Svet - Music (Juloboy Remix)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:2.002;s:7:"bitrate";d:67.28;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:2.631;s:7:"bitrate";d:85.1;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:4.959;s:7:"bitrate";d:130.665;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:5.008;s:7:"bitrate";d:137.979;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:5.194;s:7:"bitrate";d:153.522;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.458;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:32.294;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.748;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:61.059;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.998;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:84.872;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.034;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:96.322;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:4;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:1.383;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:124.806;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.48;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:140.91;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:1.953;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:173.658;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.59;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:293.31;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.613;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:233.613;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:4.897;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:472.138;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:5.172;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:641.383;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.608;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:10.905;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1376.222;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:11;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=ZlBU4l7zgWQ";s:8:"video_id";s:11:"ZlBU4l7zgWQ";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/ZlBU4l7zgWQ/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLDu-Zz3snuNMtPXYnpo5AgA1m12Iw";s:5:"title";s:30:"Kiso & Tep No - Another Friend";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.013;s:7:"bitrate";d:52.784;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.344;s:7:"bitrate";d:69.322;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.625;s:7:"bitrate";d:127.935;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.649;s:7:"bitrate";d:135.671;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.677;s:7:"bitrate";d:137.697;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.342;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:28.624;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.783;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:72.646;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.871;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:47.766;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.435;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:122.697;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.525;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:128.769;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.344;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:208.573;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:6;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.742;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:219.016;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.845;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:271.279;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.382;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:336.799;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:6.521;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:703.308;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:6.952;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:7.409;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:749.407;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:8.246;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:786.071;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:12;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=45xhAzXy7vI";s:8:"video_id";s:11:"45xhAzXy7vI";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/45xhAzXy7vI/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLC9c8XdsvwO9ykK8wJMLU8w9F8DBA";s:5:"title";s:44:"Fialta - Cars (Mark Lower Remix - Radio Cut)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.482;s:7:"bitrate";d:56.542;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.953;s:7:"bitrate";d:74.967;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:3.517;s:7:"bitrate";d:133.925;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.561;s:7:"bitrate";d:129.41;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.884;s:7:"bitrate";d:150.096;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:11:{i:0;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:2.486;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:94.321;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:1;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:2.691;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:130.406;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:2;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:3.061;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:112.564;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:3;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:4.475;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:215.152;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:6.219;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:235.607;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:6.689;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:337.673;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:6.767;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:246.043;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:7;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:12.417;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:619.943;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:13.053;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:9;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:13.415;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:505.176;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:10;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:28.035;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1036.561;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}}}}i:13;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=wiGkg0YBAZg";s:8:"video_id";s:11:"wiGkg0YBAZg";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/wiGkg0YBAZg/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLCLc15RnIgOL9ZLlfdjSKt-J4GMkg";s:5:"title";s:61:"Nicolas Haelg, Sam Halabi,  Adon - Coming Home (Original Mix)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.028;s:7:"bitrate";d:57.303;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.367;s:7:"bitrate";d:76.601;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.441;s:7:"bitrate";d:131.243;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.52;s:7:"bitrate";d:130.471;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.711;s:7:"bitrate";d:151.044;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.231;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:24.904;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.442;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:49.653;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:0.553;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:72.391;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:3;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.614;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:48.987;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:0.743;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:83.877;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.819;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:67.82;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:1.03;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:115.109;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.169;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:93.536;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:1.213;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:99.738;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:2.287;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:189.675;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:2.483;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:317.364;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:4.333;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:4.744;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:391.934;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:14;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=1W5BA0lDVLM";s:8:"video_id";s:11:"1W5BA0lDVLM";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/1W5BA0lDVLM/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLBC187pNnfHY7bJOiSa7th9vX0WCg";s:5:"title";s:70:"Mahmut Orhan & Colonel Bagshot - 6 Days (Official Video) [Ultra Music]";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.374;s:7:"bitrate";d:59.096;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.811;s:7:"bitrate";d:76.796;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:3.454;s:7:"bitrate";d:139.043;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.464;s:7:"bitrate";d:128.033;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.556;s:7:"bitrate";d:140.759;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:18:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:1.122;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:63.324;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"394";s:9:"file_size";d:1.874;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:83.862;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:2.384;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:102.942;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:2.594;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:158.429;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:4;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:2.957;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:198.76;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:5;a:6:{s:9:"format_id";s:3:"395";s:9:"file_size";d:3.206;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:176.318;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:6;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:5.185;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:383.955;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:7;a:6:{s:9:"format_id";s:3:"396";s:9:"file_size";d:5.742;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:318.983;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:8;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:6.115;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:367.92;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:9;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:8.797;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:645.951;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:10;a:6:{s:9:"format_id";s:3:"397";s:9:"file_size";d:9.782;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:570.175;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:10.174;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:10.988;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:693.049;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:13;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:14.378;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1140.882;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:14;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:15.296;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1318.24;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:15;a:6:{s:9:"format_id";s:3:"398";s:9:"file_size";d:18.536;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1110.266;s:9:"extension";s:3:"mp4";s:5:"codec";s:13:"av01.0.05M.08";}i:16;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:20.762;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1679.036;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:17;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:21.884;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:2328.488;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:15;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=CQeTSFEw5zI";s:8:"video_id";s:11:"CQeTSFEw5zI";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/CQeTSFEw5zI/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLAj1AU12yoNm5QuLOmG4wRC3HPbsg";s:5:"title";s:13:"BLR - La Luna";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.177;s:7:"bitrate";d:54.79;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.558;s:7:"bitrate";d:71.692;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.996;s:7:"bitrate";d:127.913;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.113;s:7:"bitrate";d:138.344;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:4;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:3.117;s:7:"bitrate";d:144.276;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.372;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:31.828;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.766;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:57.141;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.871;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:56.907;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.267;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:61.271;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.326;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:106.529;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:1.995;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:151.884;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:6;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.21;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:125.287;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.319;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:237.908;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:2.77;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:248.789;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:3.869;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:328.298;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.099;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:7.48;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:354.136;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:8.914;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:423.713;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:16;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=SfUt2AT6x1Y";s:8:"video_id";s:11:"SfUt2AT6x1Y";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/SfUt2AT6x1Y/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLDekZQxyI5JEOqKaFhZPZ3_uLUMAQ";s:5:"title";s:33:"Costa Mee - Waiting For The Light";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.812;s:7:"bitrate";d:55.088;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:2.404;s:7:"bitrate";d:72.82;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:4.469;s:7:"bitrate";d:136.235;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:4.503;s:7:"bitrate";d:128.212;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:4.802;s:7:"bitrate";d:141.035;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:1.334;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:45.871;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:2.315;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:79.096;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:3.163;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:105.845;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:3.178;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:109.167;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:4.333;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:233.42;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:6.585;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:233.953;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:7.286;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:503.479;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:11.603;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:407.704;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:13.334;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1033.054;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:15.243;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:10;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:26.01;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:890.765;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:11;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:29.797;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:2123.837;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:54.621;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1744.182;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:17;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=F2vuY7IHeTw";s:8:"video_id";s:11:"F2vuY7IHeTw";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/F2vuY7IHeTw/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLAfU8XxXbFgfKveDL7UrjojxKQN0A";s:5:"title";s:16:"Bolier - Grow Up";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.053;s:7:"bitrate";d:57.514;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.379;s:7:"bitrate";d:74.864;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.539;s:7:"bitrate";d:131.33;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.624;s:7:"bitrate";d:133.72;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.705;s:7:"bitrate";d:143.855;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.348;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:30.57;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.632;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:49.315;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.762;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:72.286;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.346;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:76.596;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.429;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:125.875;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.541;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:157.895;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.684;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:279.273;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:4.054;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:293.486;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:5.182;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:601.267;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:7.682;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:716.977;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.755;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:9.086;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1106.76;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:20.958;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1754.026;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:18;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=LMg6vztjxkQ";s:8:"video_id";s:11:"LMg6vztjxkQ";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/LMg6vztjxkQ/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLB9nELOA97odmGd0OViVDvyt5lWLA";s:5:"title";s:34:"Trinix - Otherside (ft. Ope Smith)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.118;s:7:"bitrate";d:58.659;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.472;s:7:"bitrate";d:77.156;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.594;s:7:"bitrate";d:136.744;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.615;s:7:"bitrate";d:128.039;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.895;s:7:"bitrate";d:150.029;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.377;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:34.609;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.771;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:89.092;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.918;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:79.897;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.042;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:122.872;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.697;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:147.745;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.116;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:215.301;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:3.058;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:332.413;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:3.648;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:386.156;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:6.393;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:759.059;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.3;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:10;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:7.869;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:755.764;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:11;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:13.679;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1699.35;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:14.509;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1275.339;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:19;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=RcXGB6ZeRwQ";s:8:"video_id";s:11:"RcXGB6ZeRwQ";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/RcXGB6ZeRwQ/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLCjC7wOQpCZf9p-WOAPV94zjbY7Mw";s:5:"title";s:34:"Diego Power - Hello (Original Mix)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.472;s:7:"bitrate";d:59.292;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.938;s:7:"bitrate";d:76.773;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:3.448;s:7:"bitrate";d:126.593;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.774;s:7:"bitrate";d:130.577;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.826;s:7:"bitrate";d:144.84;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.283;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:25.438;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.36;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:39.621;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:0.505;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:57.041;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:3;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:0.656;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:67.847;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:4;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.737;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:40.748;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:5;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.846;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:41.354;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:1.071;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:104.132;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.596;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:64.826;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.25;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:92.221;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:3.451;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:141.067;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:3.987;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:349.97;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:5.379;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:7.37;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:409.344;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:20;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=4giOQb2pZUQ";s:8:"video_id";s:11:"4giOQb2pZUQ";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/4giOQb2pZUQ/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLCgEWSBURat0A9y9Qhbx3KUCf0Bxw";s:5:"title";s:34:"Max Oazo ft. CAMI - Wonderful Life";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.156;s:7:"bitrate";d:56.044;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.535;s:7:"bitrate";d:74.56;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.838;s:7:"bitrate";d:130.518;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.985;s:7:"bitrate";d:141.637;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.032;s:7:"bitrate";d:146.591;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.386;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:33.675;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.828;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:61.269;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:2;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.844;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:63.545;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.893;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:78.741;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.569;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:123.466;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.671;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:144.681;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.431;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:191.489;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.63;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:232.269;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.926;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:342.12;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:5.207;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:476.973;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.489;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:8.126;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:644.977;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:11.382;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:998.066;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:21;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=_k2omFLKex0";s:8:"video_id";s:11:"_k2omFLKex0";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/_k2omFLKex0/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLAlkWD7KoMsL5LPKQYralm6qClirw";s:5:"title";s:36:"Jako Diaz & Stephen Ingram - You Say";s:7:"formats";a:2:{s:5:"audio";a:4:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.404;s:7:"bitrate";d:62.259;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.848;s:7:"bitrate";d:81.196;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.3;s:7:"bitrate";d:128.103;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.626;s:7:"bitrate";d:156.579;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.388;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:23.843;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.568;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:36.773;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.783;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:42.523;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.806;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:41.709;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.363;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:94.642;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.784;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:92.774;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.252;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:171.63;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.749;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:177.204;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.283;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:303.327;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:4.414;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:233.291;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:5.445;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:586.623;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:11;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.43;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:9.632;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:539.067;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:22;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=6UTwRVenN-E";s:8:"video_id";s:11:"6UTwRVenN-E";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/6UTwRVenN-E/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLAmtJmuM0YmtOt-ZyUXMQPLwWeDWQ";s:5:"title";s:53:"Stephen Murphy - Set You Free (ChillYourMind Release)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:0.976;s:7:"bitrate";d:52.226;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.288;s:7:"bitrate";d:68.943;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.461;s:7:"bitrate";d:131.244;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.544;s:7:"bitrate";d:127.952;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.569;s:7:"bitrate";d:138.02;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:1.789;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:96.789;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:1;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:2.008;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:112.025;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:2;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:4.038;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:228.091;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:4.093;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:232.803;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:7.54;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:417.467;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:5;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:10.993;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:625.369;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:6;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:13.898;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:758.077;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:7;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:14.068;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:8;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:22.064;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:1271.8;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:27.394;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1524.878;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:39.222;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:2257.573;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:11;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:48.314;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:2656.3;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:54.309;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:3413.605;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}}}}i:23;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=lckYW__7tmE";s:8:"video_id";s:11:"lckYW__7tmE";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/lckYW__7tmE/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLD7-PM_tlfatae8NQHm5BMZZUMi8A";s:5:"title";s:29:"Tchami - Adieu (TEEMID Remix)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.856;s:7:"bitrate";d:57.188;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:2.437;s:7:"bitrate";d:73.838;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:4.545;s:7:"bitrate";d:128.083;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:4.756;s:7:"bitrate";d:137.884;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:4.839;s:7:"bitrate";d:144.056;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.481;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:30.564;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.702;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:49.711;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.977;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:75.855;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.715;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:119.222;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.724;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:133.183;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:3.722;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:223.652;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:5.272;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:451.706;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:7.156;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:607.494;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:12.678;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:9;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:12.97;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1331.428;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:10;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:18.014;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:1540.467;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:11;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:32.432;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:3466.803;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:62.351;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:2755.816;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:24;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=rr0vQDlfQ68";s:8:"video_id";s:11:"rr0vQDlfQ68";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/rr0vQDlfQ68/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLDRRA_RmiCI1kwM5Gjcf4vxLkmiGw";s:5:"title";s:38:"Big Z - Losing Control (Stisema Remix)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:0.978;s:7:"bitrate";d:56.023;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.287;s:7:"bitrate";d:73.514;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:2.414;s:7:"bitrate";d:127.912;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:3;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:2.453;s:7:"bitrate";d:135.101;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:2.536;s:7:"bitrate";d:138.972;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.29;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:30.289;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.462;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:46.13;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.638;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:67.237;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:0.971;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:94.199;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:4;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:1.052;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:65.275;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:5;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:1.742;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:170.91;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:6;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:2.146;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:129.328;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:3.115;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:196.454;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:3.198;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:362.787;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:5.57;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:668.153;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:10;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:6.634;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}i:11;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:7.068;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:509.184;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:13.243;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:1145.18;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}}}}i:25;a:5:{s:3:"url";s:43:"https://www.youtube.com/watch?v=XKxRb0AWKQQ";s:8:"video_id";s:11:"XKxRb0AWKQQ";s:9:"thumbnail";s:147:"https://i.ytimg.com/vi/XKxRb0AWKQQ/hqdefault.jpg?sqp=-oaymwEiCKgBEF5IWvKriqkDFQgBFQAAAAAYASUAAMhCPQCAokN4AQ==&rs=AOn4CLDr_AlUCoxsv86bAAH5mEv-kButqw";s:5:"title";s:42:"The Him - Broken Love (feat. Parson James)";s:7:"formats";a:2:{s:5:"audio";a:5:{i:0;a:5:{s:9:"format_id";s:3:"249";s:9:"file_size";d:1.245;s:7:"bitrate";d:55.278;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:1;a:5:{s:9:"format_id";s:3:"250";s:9:"file_size";d:1.644;s:7:"bitrate";d:72.516;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}i:2;a:5:{s:9:"format_id";s:3:"171";s:9:"file_size";d:3.089;s:7:"bitrate";d:134.475;s:9:"extension";s:4:"webm";s:5:"codec";s:6:"vorbis";}i:3;a:5:{s:9:"format_id";s:3:"140";s:9:"file_size";d:3.148;s:7:"bitrate";d:128.059;s:9:"extension";s:3:"m4a";s:5:"codec";s:9:"mp4a.40.2";}i:4;a:5:{s:9:"format_id";s:3:"251";s:9:"file_size";d:3.245;s:7:"bitrate";d:140.062;s:9:"extension";s:4:"webm";s:5:"codec";s:4:"opus";}}s:5:"video";a:13:{i:0;a:6:{s:9:"format_id";s:3:"160";s:9:"file_size";d:0.408;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:32.663;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d400c";}i:1;a:6:{s:9:"format_id";s:3:"133";s:9:"file_size";d:0.815;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:64.705;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d4015";}i:2;a:6:{s:9:"format_id";s:3:"278";s:9:"file_size";d:0.85;s:10:"resolution";s:14:"256x144 (144p)";s:7:"bitrate";d:59.054;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:3;a:6:{s:9:"format_id";s:3:"242";s:9:"file_size";d:0.856;s:10:"resolution";s:14:"426x240 (240p)";s:7:"bitrate";d:60.792;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:4;a:6:{s:9:"format_id";s:3:"134";s:9:"file_size";d:1.411;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:119.963;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401e";}i:5;a:6:{s:9:"format_id";s:3:"243";s:9:"file_size";d:1.867;s:10:"resolution";s:14:"640x360 (360p)";s:7:"bitrate";d:125.137;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:6;a:6:{s:9:"format_id";s:3:"135";s:9:"file_size";d:2.086;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:171.087;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:7;a:6:{s:9:"format_id";s:3:"244";s:9:"file_size";d:2.561;s:10:"resolution";s:14:"854x480 (480p)";s:7:"bitrate";d:195.096;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:8;a:6:{s:9:"format_id";s:3:"136";s:9:"file_size";d:2.631;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:273.276;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.4d401f";}i:9;a:6:{s:9:"format_id";s:3:"247";s:9:"file_size";d:3.451;s:10:"resolution";s:15:"1280x720 (720p)";s:7:"bitrate";d:254.709;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:10;a:6:{s:9:"format_id";s:3:"137";s:9:"file_size";d:3.934;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:356.356;s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.640028";}i:11;a:6:{s:9:"format_id";s:3:"248";s:9:"file_size";d:6.133;s:10:"resolution";s:17:"1920x1080 (1080p)";s:7:"bitrate";d:403.223;s:9:"extension";s:4:"webm";s:5:"codec";s:3:"vp9";}i:12;a:6:{s:9:"format_id";s:2:"18";s:9:"file_size";d:7.965;s:10:"resolution";s:16:"640x360 (medium)";s:7:"bitrate";s:7:"unknown";s:9:"extension";s:3:"mp4";s:5:"codec";s:11:"avc1.42001E";}}}}}}');

        }

        echo json_encode($response);
    }


    private function getVideoTitleAndThumbnail($video_id){

        exec($this->youtube_dl.' -j '.$video_id,$output, $return_var);
        $resp = json_decode($output[0]);

        return array(
            'title'     =>  $resp->fulltitle,
            'thumbnail' =>  $resp->thumbnail
        );
    }

    /*
     * Download stream by format
     */
    public function downloadStream($format,$format_type,$url,$random_name = false,$dir = false,$playlist_id = false){

        if(!$dir){
            $dir = '';
        }

        if($random_name){
            $random_name = time();
        }

        $download_command = $this->youtube_dl.' --restrict-filenames -o "'.$this->server_dir.'\\'.$dir.'%(title)s'.$random_name.'.%(ext)s" -f '.$format.' '.$url;

        $video_format_id = $format_type == 'video'?$format:NULL;
        $audio_format_id = $format_type == 'audio'?$format:NULL;

        parse_str( parse_url( $url, PHP_URL_QUERY ), $match );

        $params = array(
            'playlist_id'       =>  $playlist_id?$playlist_id:NULL,
            'video_id'          =>  $match['v'],
            'video_format_id'   =>  $video_format_id,
            'audio_format_id'   =>  $audio_format_id
        );

        $dbv = new \App\Videos();
        $task_id = $dbv->insertVideoIntoQueue($download_command,$params);

        return $task_id;
    }

    //default approach do not do anything in regards to selected video just download recommended format
    private function defaultApproach($url,$playlist_dir = false,$format=false,$playlist = false){

        $video_format_id = null;
        $audio_format_id = null;

        if(!$format){

            $format_param   = '';
            $format         = '%(ext)s';

        }elseif($format == 'mp3'){

            $audio_format_id = 'mp3';
            $format_param = ' --ignore-errors -f bestaudio --extract-audio --audio-format mp3 --audio-quality 0';

        }elseif($format == 'mp4'){

            $video_format_id = 'mp4';
            $format_param = ' -f bestvideo';

        }

        $download_command = $this->youtube_dl.' --restrict-filenames'.$format_param.' -o "'.$this->server_dir.DIRECTORY_SEPARATOR.$playlist_dir.'%(title)s.'.$format.'" '.$url;


        parse_str( parse_url( $url, PHP_URL_QUERY ), $match );

        $params = array(
            'playlist_id'       =>  $playlist?$playlist:NULL,
            'video_id'          =>  $match['v'],
            'video_format_id'   =>  $video_format_id,
            'audio_format_id'   =>  $audio_format_id
        );


        $dbv = new \App\Videos();
        $task_id = $dbv->insertVideoIntoQueue(
            $download_command,
            $params
        );

        return $task_id;

    }

    private function zipFiles($folder_to_zip){

        $rootPath           = realpath($folder_to_zip);
        $zip_file_location  = 'downloads'.DIRECTORY_SEPARATOR.'zips'.DIRECTORY_SEPARATOR.basename($folder_to_zip).'.zip';

        // Initialize archive object
        $zip = new \ZipArchive();
        $zip->open($zip_file_location, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Create recursive directory iterator
        /** @var SplFileInfo[] $files */
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($rootPath),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file)
        {
            // Skip directories (they would be added automatically)
            if (!$file->isDir())
            {
                // Get real and relative path for current file
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($rootPath) + 1);

                // Add current file to archive
                $zip->addFile($filePath, $relativePath);
            }
        }

        $file_to_download = $zip->filename;

        // Zip archive will be created only after closing object
        $zip->close();

        return $file_to_download;

    }
    public function downloadPlaylist(Request $req){

        $pd = (time()).'_'.bin2hex(openssl_random_pseudo_bytes(10));
        $playlist_dir = $this->server_dir.DIRECTORY_SEPARATOR.$pd;

        @mkdir($playlist_dir);

        $files_downloaded = array();

        foreach($req->input('videos') as $vid){

            //audio & video
            if($vid['method'] == 'manual' && $vid['audio_stream'] != 'false' && $vid['video_stream'] != 'false'){

                $audio_stream = $this->downloadStream(
                    $vid['audio_stream'],
                    'audio',
                    'https://www.youtube.com/watch?v='.$vid['video_id'],
                    $random_name = true,
                    $pd.DIRECTORY_SEPARATOR,
                    $req->playlist
                );

                $video_stream = $this->downloadStream(
                    $vid['video_stream'],
                    'video',
                    'https://www.youtube.com/watch?v='.$vid['video_id'],
                    false,
                    $pd.DIRECTORY_SEPARATOR,
                    $req->playlist
                );

            }else if($vid['method'] == 'manual' && $vid['audio_stream'] != 'false' && $vid['video_stream'] == 'false'){

                $filename = $this->downloadStream(
                    $vid['audio_stream'],
                    'audio',
                    'https://www.youtube.com/watch?v='.$vid['video_id'],
                    false,
                    $pd.DIRECTORY_SEPARATOR,
                    $req->playlist
                );

                //video
            }else if($vid['method'] == 'manual' && $vid['audio_stream'] == 'false' && $vid['video_stream'] != 'false'){

                $filename = $this->downloadStream(
                    $vid['video_stream'],
                    'video',
                    'https://www.youtube.com/watch?v='.$vid['video_id'],
                    false,
                    $pd.DIRECTORY_SEPARATOR,
                    $req->playlist
                );

                //default
            }else if($vid['method'] == 'audio'){

                $filename = $this->defaultApproach(
                    'https://www.youtube.com/watch?v='.$vid['video_id'],
                    $pd.DIRECTORY_SEPARATOR,
                    'mp3',
                    $req->playlist
                );

                $this->convertAudio($playlist_dir.DIRECTORY_SEPARATOR,$filename);

            }else if($vid['method'] == 'video'){

                $filename = $this->defaultApproach(
                    'https://www.youtube.com/watch?v='.$vid['video_id'],
                    $pd.DIRECTORY_SEPARATOR,
                    'mp4',
                    $req->playlist
                );

            }else{

                $filename = $this->defaultApproach(
                    'https://www.youtube.com/watch?v='.$vid['video_id'],
                    $pd.DIRECTORY_SEPARATOR,
                    '',
                    $req->playlist
                );

            };

        };

        return $req->playlist;
/*

        $file_to_download = $this->zipFiles($playlist_dir);

        @unlink($playlist_dir);

        return json_encode(
            array(
                'status'        =>  true,
                'download_url'  =>  'http://youtubemate/videos/downloadGateway?file='.basename($file_to_download),
                'files'         =>  $files_downloaded
            )
        );
*/
    }

    public function downloadSingleVideoByFormat(Request $req){

        $video_format   = $req->input('video_format');
        $audio_format   = $req->input('audio_format');
        $url            = $req->input('video_url');
        $is_default     = $req->input('is_default');
        $status         = false;

        //remove any additional parameters if any
        $p_url          = parse_url($url);

        $query          = explode("&",$p_url['query']);
        $video_id       = $query[0];

        $p_url['scheme']=$p_url['scheme'].'://';
        unset($p_url['query']);

        //url like https://www.youtube.com/watch?v=9w4LM-qSk5k
        $url = trim(implode("",$p_url).'?'.$video_id);

        //default approach do not do anything in regards to selected video just download recommended format
        if($is_default == 'true'){
            $filename = $this->defaultApproach($url);
        }else{
            //merge streams
            if(!empty($video_format) && !empty($audio_format)){

                $audio_stream = $this->downloadStream($audio_format,'audio',$url,$random_name = true);
                $video_stream = $this->downloadStream($video_format,'video',$url);

                if($audio_stream && $video_stream){
                    $filename = $this->mergeVideo($audio_stream,$video_stream);
                }else{
                    echo 'error';
                }

            }elseif(!empty($audio_format)){

                $audio_stream = $this->downloadStream($audio_format,'audio',$url);
                $filename = $audio_stream;

            }elseif(!empty($video_format)){

                $video_stream = $this->downloadStream($video_format,'video',$url);
                $filename = $video_stream;

            }
        }

        if($filename){
            $status = true;
        }

        $response = array(
            'status'        =>  $status,
            'download_url'  =>  'http://youtubemate/videos/downloadGateway?file='.$filename,
        );

        echo json_encode($response);

    }

    private function handleFileHeaders($file_location,$file_name){

        header('Content-type: '.mime_content_type($file_location));
        header('Content-Disposition: binary; filename="'.$file_name.'"');

        $handle = fopen($file_location, 'rb');
        $buffer = '';
        while (!feof($handle)) {
            $buffer = fread($handle, 4096);
            echo $buffer;
            ob_flush();
            flush();
        }

        fclose($handle);

    }

    public function downloadGateway(Request $p){

        $file_name   = $p->input('file');

        if(!empty($file_name)){

            $server_file_location = public_path().DIRECTORY_SEPARATOR.'downloads'.DIRECTORY_SEPARATOR.$file_name;
            $zip_location = public_path().DIRECTORY_SEPARATOR.'downloads'.DIRECTORY_SEPARATOR.'zips'.DIRECTORY_SEPARATOR.$file_name;


            if(1==1){
                if (is_file($server_file_location) && is_readable($server_file_location))
                {
                    $this->handleFileHeaders($server_file_location,basename($file_name));
                }elseif(is_file($zip_location) && is_readable($zip_location)){
                    $this->handleFileHeaders($zip_location,basename($file_name));
                }else{
                    // file does not exist
                    header("HTTP/1.0 404 Not Found");
                    exit;
                }
            }else{
                return 403;//forbidden
            }

        }else{
            return 404;//not found
        }
    }

    public function test(){
        // exec($ffmpeg_merge,$output, $return_var);

        echo 'asd';

        return;
    }

}

