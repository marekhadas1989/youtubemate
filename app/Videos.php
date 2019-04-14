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
}
