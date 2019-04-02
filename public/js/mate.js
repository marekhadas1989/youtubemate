'use strict';
var mate = {
    init:function(){
        console.warn('YouTube Mate Init Started');
        for(var a of ['events']){
            mate[a]();
        }
    },
    ajax:function(){

        $.ajax({
            'method'    :   arguments[0],
            'dataType'  :   arguments[1],
            'success'   :   arguments[2],
            'url'       :   arguments[3],
            'params'    :   arguments[4]
        })
    },
    matchVideo:function(){

    },
    getLocation:function(url) {
        var p = /^(?:https?:\/\/)?(?:www\.)?(?:youtu\.be\/|youtube\.com\/(?:embed\/|v\/|watch\?v=|watch\?.+&v=))((\w|-){11})(?:\S+)?$/;
        var regPlaylist = /[?&]list=([^#\&\?]+)/;

        if(url.match(p)){
            return {
                'video_id':RegExp.$1,
                'playlist_id':url.match(regPlaylist)?url.match(regPlaylist)[1]:false
            }
        }
        return false;
    },
    parseVideoInfo(){

    },
    getVideoData:function(params){
        var response = this.ajax(
            'post',
            'json',
            function(){
                alert('success callback');
            },
            '/videos/getVideoInfo',
            params
        );
    },
    checkVideoInfo:function(videoID){

        var _this              = this,
            youtube_video_data = this.getLocation(videoID),
            params = {
                video_id    :   youtube_video_data.video_id,
                playlist_id :   youtube_video_data.playlist_id
            }

            if(!youtube_video_data){
                alertify.alert('Error','Invalid YouTube URL');
                return false;
            };

            if(params.playlist_id){
                alertify.confirm(
                    'Confirm',
                    'Selected video is a part of playlist, do you wish to download entire playlist',
                    function(){
                        alertify.success('Getting playlist info');
                        _this.getVideoData(params);
                    },
                    function(){
                        alertify.success('Getting video info');
                        params.playlist_id = false;
                        _this.getVideoData(params);
                    }
                ).set('labels', {ok:'Download Playlist', cancel:'Video Only'});

            }else{
                _this.getVideoData(params);
            }

        //https://www.youtube.com/watch?v=kAQ-LnIEaXg&list=RDkAQ-LnIEaXg&start_radio=1
    },
    events:function(){

        var _this = this;

        $('.downloadVideo').on('click',function(){
            var videoID = $(this).parent().siblings().find('input').val();

            if(!videoID.length){
                alertify.alert('Error','Please enter your video id or url');
            }else{
                _this.checkVideoInfo(videoID);
            }
        })

    },
}