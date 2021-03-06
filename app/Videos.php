<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Videos extends Model
{
    public function getRecentlyAdded() {


        $data = DB::table('youtube_videos')
            ->select('*')
           # ->where('active',1)
            ->orderByRaw('date_created DESC')
            ->limit(8)
            ->get();

        return $data;

    }

    public function insertVideoIntoQueue($command,Array $params){

        $values = array(
            'command'=>$command
        );

        if(!empty($params)){
            $values = array_merge($values,$params);
        }

        $id = DB::table('youtube_videos')->insertGetId($values);

        return $id;
    }

    public function setStatus($id,$status){

        DB::table('youtube_videos')
            ->where('id', $id)
            ->update(['status' => $status]);
    }

    public function thumbnailssToDownload(){

        $data = DB::table('youtube_videos')
            ->select('*')
            //->where('status',1)
            ->whereNull('thumbnail')
            ->orderByRaw('date_created ASC')
            ->limit(10)
            ->get();

        return $data;
    }

    public function videosToDownload() {

        $data = DB::table('youtube_videos')
            ->select('*')
            //->where('status',1)
            ->whereNull('status')
            ->orderByRaw('date_created ASC')
            ->limit(10)
            ->get();

        return $data;

    }
}
