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
            'data'      :   arguments[4],
            'beforeSend': function() {
                $('.parent').show();
            }
        }).fail(function( jqXHR, textStatus ) {
            alert( "Request failed: ");
        }).done(function( data ) {
            $('.parent').hide();
        });

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
    displaySingleVideoInfo(audio,video){

        var audioHTML = '',
            videoHTML = '',
            iter      = 1,
            iterV     = 1;

        for(var a in audio){
            audioHTML+=
                '<tr>'+
                    '<th scope="row">'+iter+'</th>'+
                    '<td>'+audio[a].file_size+' MB</td>'+
                    '<td>'+audio[a].bitrate+' KB/s</td>'+
                    '<td>'+audio[a].extension+'</td>'+
                    '<td>'+audio[a].codec+'</td>'+
                    '<td><input type="radio" name="audio" value="'+audio[a].format_id+'"></td>'+
                '</tr>';
            iter++;
        }
        for(var a in video){
            //singleVideoFormats
            videoHTML+=
                '<tr>'+
                    '<th scope="row">'+iterV+'</th>'+
                    '<td>'+video[a].file_size+' MB</td>'+
                    '<td>'+video[a].resolution+'</td>'+
                    '<td>'+video[a].bitrate+' KB/s</td>'+
                    '<td>'+video[a].extension+'</td>'+
                    '<td><input type="radio" name="audio" value="'+video[a].format_id+'"></td>'+
                '</tr>';
            iterV++;
        }

        $('.singleAudioFormats').html(audioHTML);
        $('.singleVideoFormats').html(videoHTML);


        $('.DataTable').DataTable( {
            "searching" : false,
            "paging"    : false,
            "bInfo"     : false,
            "responsive": true
        });

    },
    getVideoData:function(params){

        var response = this.ajax(
            'post',
            'json',
            function(data){

                $('.singleVideoThumbnail').attr('src',data.available[0].thumbnail);
                $('.singleVideoTitle').text(data.available[0].title);

                var audio = data.available[0].formats.audio,
                    video = data.available[0].formats.video;

                mate.displaySingleVideoInfo(audio,video);

                $('.boxVideos').fadeIn();
            },
            '/videos/displayVideosInfo',
            params
        );

    },
    checkVideoInfo:function(videoID){

        var _this              = this,
            youtube_video_data = this.getLocation(videoID),
            params = {
                video_id    :   youtube_video_data.video_id,
                playlist_id :   youtube_video_data.playlist_id,
                url         :   videoID
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
                alertify.alert('Error','Please enter valid YouTube URL');
            }else{
                _this.checkVideoInfo(videoID);
            }
        })

    },
}