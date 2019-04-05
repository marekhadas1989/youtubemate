<?php

namespace App\Http\Controllers;
use Symfony\Component\HttpFoundation\Request;

class Videos extends Controller
{

    private $server_dir = "C:\Users\wildsnaske\Documents\GitHub\youtubemate\public\downloads";
    private $youtube_dl = "C:\Users\wildsnaske\Documents\GitHub\youtubemate\youtube-dl.exe";


    private function va($p){

        echo '<pre>';
        print_r($p);
        echo '</pre>';

    }

    /*
     * Process playlist based on selected URL
     */
    private function getPlaylistInfo($url){

        $code       = file_get_contents($url);

        $dochtml    = new \DOMDocument();
        $code       = mb_convert_encoding($code, 'HTML-ENTITIES', "UTF-8");

        $dochtml->loadHTML($code);

        $xml = simplexml_import_dom($dochtml);
        $element_video = $xml->xpath("//*[@id='player-playlist']/div/div/div/ol/li/a");

        $available = array();
        $deleted = array();

        foreach($element_video as $el){

            $video_id       = (array)($el->attributes()->href);
            $thumbnail      = (array)$el->span->span->span->img->attributes()->{'data-thumb'};
            $thumbnail_src  = (array)$el->span->span->span->img->attributes()->{'src'};
            $tt             = (array)$el->div->h4;
            $title          = trim($tt[0]);

            if($title == 'Deleted video'){

                $available[] = array(
                    'video_id'  =>  "https://www.youtube.com/".$video_id[0],
                    'thumbnail' =>  !empty($thumbnail[0])?$thumbnail[0]:$thumbnail_src[0],
                    'title'     =>  $title,
                    'formats'   =>  $this->parseAvailableFormatsJSON("https://www.youtube.com/".$video_id[0])
                );

            }else{

                $deleted[]= array(
                    'video_id'  =>  "https://www.youtube.com/".$video_id[0],
                    'thumbnail' =>  !empty($thumbnail[0])?$thumbnail[0]:$thumbnail_src[0],
                    'title'     =>  $title
                );

            }
        };

        return array(
            'deleted'   => $deleted,
            'available' => $available
        );

    }

    /*
     * Parse available video/audio formats based on specified url
     */
    private function parseAvailableFormatsJSON($video){

        //format code  extension  resolution note
        exec($this->youtube_dl.' -j '.$video,$output, $return_var);

        $response = json_decode($output[0]);

        $processed_videos   = array();
        $processed_audio    = array();

        foreach($response->formats as $f){

            if(!empty($f->height)){
                //video

                $processed_videos[$f->format_id] = array(
                    'format_id'     =>  $f->format_id,
                    'file_size'     =>  round($f->filesize/1024/1024,3),//MB
                    'resolution'    =>  $f->width.'x'.$f->height.' ('.$f->format_note.')',//'KB/s'
                    'bitrate'       =>  $f->tbr,
                    'extension'     =>  $f->ext,
                    'codec'         =>  $f->vcodec,
                );


            }else{
                //audio

                $processed_audio[$f->format_id] = array(
                    'format_id'     =>  $f->format_id,
                    'file_size'     =>  round($f->filesize/1024/1024,3),//MB
                    'bitrate'       =>  $f->tbr,//'KB/s'
                    'extension'     =>  $f->ext,
                    'codec'         =>  $f->acodec,
                );

            }
        };

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
         //   $response = $this->getPlaylistInfo($url);
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

    public function test(){

        $this->va();return;
    }

}

