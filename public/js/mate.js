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
            $('.parent').hide();
        }).done(function( data ) {
            $('.parent').hide();
            $(window).scrollTop($('.boxVideos').position().top+$('.boxVideos .container').eq(0).height());
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
    displayPlaylistInfo:function(data){
        console.log(data);
    },
    displaySingleVideoInfo:function(data,url){

        $('.singleVideoThumbnail').attr('src',data.available[0].thumbnail);
        $('.singleVideoTitle').text(data.available[0].title);

        $('.downloadSingle').attr('video_url',url);

        var audio = data.available[0].formats.audio,
            video = data.available[0].formats.video;

        var audioHTML = '',
            videoHTML = '',
            iter      = 1,
            iterV     = 1;

        for(var a in audio){
            audioHTML+=
                '<tr extension="'+audio[a].extension+'">'+
                    '<th scope="row">'+iter+'</th>'+
                    '<td>'+(audio[a].file_size == 'Unknown'?audio[a].file_size:audio[a].file_size+' MB')+'</td>'+
                    '<td>'+audio[a].bitrate+' KB/s</td>'+
                    '<td>'+audio[a].extension+'</td>'+
                    '<td>'+audio[a].codec+'</td>'+
                    '<td><input type="radio" data="audio" name="audio_selected_format" value="'+audio[a].format_id+'"></td>'+
                '</tr>';
            iter++;
        }
        for(var a in video){
            //singleVideoFormats
            videoHTML+=
                '<tr extension="'+video[a].extension+'">'+
                    '<th scope="row">'+iterV+'</th>'+
                    '<td>'+(video[a].file_size == 'Unknown'?video[a].file_size:video[a].file_size+' MB')+'</td>'+
                    '<td>'+video[a].resolution+'</td>'+
                    '<td>'+video[a].bitrate+' KB/s</td>'+
                    '<td>'+video[a].extension+'</td>'+
                    '<td><input type="radio" data="video" name="video_selected_format" value="'+video[a].format_id+'"></td>'+
                '</tr>';
            iterV++;
        }

        $('.singleAudioFormats').html(audioHTML);
        $('.singleVideoFormats').html(videoHTML);

        if ( !$.fn.dataTable.isDataTable( '.DataTable' ) ) {
            $('.DataTable').DataTable( {
                "searching" : false,
                "paging"    : false,
                "bInfo"     : false,
                "responsive": true
            });
        }

        $('.boxVideos').fadeIn();
    },
    getVideoData:function(params){

        var response = this.ajax(
            'post',
            'json',
            function(data){
                try{

                    if(params.playlist_id != false){
                        mate.displayPlaylistInfo(data);
                    }else{
                        mate.displaySingleVideoInfo(data,params.url);
                    };

                }catch(e){
                    console.warn(e);
                }
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
    mapExtensions:function(ext,source_box){

        var m = {
            'm4a':[
                'mp4','m4a',
            ],
            'mp4':[
                'mp4','m4a'
            ],
            'webm':[
                'webm'
            ]
        }

        if(ext in m){

            var _class = '.'+(source_box == 'singleAudioFormats'?'videoTable':'audioTable')+' tbody tr';

            $(_class).each(function(){

                var element_extension = $(this).attr('extension');

                if(m[ext].indexOf(element_extension) == -1){
                    $(this).hide();
                }else{
                    $(this).show();
                }

            })

        }
        //do not map, one size fits all}

    },
    resetSelections:function(){

        $('.selectedAudio,.selectedVideo').removeClass('selectedAudio').removeClass('selectedVideo');
        $('.formatsBox').find('tr').show();
        $('.formatsBox').find('input').removeAttr('checked');

    },
    events:function(){

        var _this = this;

        $('input[name="format_method"]').on('change',function(){

            //reset previous selections
            _this.resetSelections();

            if($(this).val() == 1){
                $('.formatsBox').addClass('disabledBox');
            }else{
                $('.formatsBox').removeClass('disabledBox');
            }

        })

        $('.boxVideos').on('click','tr',function(e){

            var extension       = $(this).attr('extension'),
                audio_or_video  = $(this).parent().attr('class');

            //if box is disabled prevent any further actions
            if($('.disabledBox').length > 0){
                return false;
            }

            //revert extension filter
            if($(this).hasClass('selectedVideo') || $(this).hasClass('selectedAudio')){
                $('.audioTable,.videoTable').find('tr').show();
            }

            if($(this).hasClass('selectedVideo')){

                $(this).removeClass('selectedVideo');
                $(this).find('input[type="radio"]').removeAttr('checked');

                return false;

            }else if($(this).hasClass('selectedAudio')){

                $(this).removeClass('selectedAudio');
                $(this).find('input[type="radio"]').removeAttr('checked');

                return false;
            }

            //filter extensions per format
            _this.mapExtensions(extension,audio_or_video);

            var parentBox = $(this).parentsUntil('formatsBox').parent();

            if(parentBox.hasClass('audioTable')){
                $('.audioTable').find('.selectedAudio').removeClass('selectedAudio');
                $(this).addClass('selectedAudio');
                $('.audioTable').find('input[name="audio_selected_format"]').removeAttr('checked');
            }else{
                $('.videoTable').find('.selectedVideo').removeClass('selectedVideo');
                $(this).addClass('selectedVideo');
                $('.videoTable').find('input[name="video_selected_format"]').removeAttr('checked');
            }

            $(this).children().find('input').attr('checked','checked');

        })

        $('.downloadSingle').on('click',function(){

            var selected_video_format = $("input[name='video_selected_format']").filter(":checked").val(),
                selected_audio_format = $("input[name='audio_selected_format']").filter(":checked").val(),
                video_url             = $(this).attr('video_url');

            var isDefault = $('input[name="format_method"]').filter(":checked").val() == 2?false:true;

            if(typeof selected_video_format == 'undefined' && typeof selected_audio_format == 'undefined' && !isDefault){
                alertify.alert('Error','Please select at least one format from the list or use default option');
            }else{
                _this.ajax(
                    'post',
                    'json',
                    function(data){

                        if(data.status){
                            window.open(data.download_url,'_blank');
                        }else{
                            alertify.error('Something went wrong, please try again later.');
                        }

                    },
                    '/videos/downloadSingleVideoByFormat',
                    {
                        video_format : selected_video_format,
                        audio_format : selected_audio_format,
                        video_url    : video_url,
                        is_default   : isDefault
                    }
                )
            };
        })

        $('.urlLink').on('keyup',function(e){
            if(e.keyCode == 13){
                $('.downloadVideo').click();
            };
        })

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