'use strict';
var mate = (function(){

    var _public = {
        init:function(){
            console.warn('YouTube Mate Init Started');

            for(var a of ['events']){
                _private[a]();
            }
        }
    }

    var _private = {
        playlist:{},
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

            var _this            = this;

            var videos_available = data.available,
                deleted_videos   = data.deleted;

            var deleted_titles   = [];

            if(deleted_videos.length > 0){

                //videos removed due to copyright infringements

                for(var b in deleted_videos){
                    deleted_titles.push(deleted_videos[b].title);
                }

            }

            var playlistHTML = '';
            if(videos_available.length > 0){
                for(var a in videos_available){

                    _this.playlist[videos_available[a].video_id] = videos_available[a];

                    playlistHTML+='' +
                        '<div class="col-xs-2 col-sm-3 col-md-2 col-lg-2 ytbPlaylistItem" method="default" youtube_id="'+videos_available[a].video_id+'">'+
                            '<label>'+
                                '<p><input type="checkbox" checked="checked"><p>'+
                                '<img class="img-fluid" src="'+videos_available[a].thumbnail+'">'+
                                '<h6>'+videos_available[a].title+'</h6>'+
                            '</label>'+
                        '</div>';

                }

                $('.playlistBox').append(playlistHTML);
            }

        },
        generateAudioTable:function(audio){

            var audioHTML = '',
                iter      = 1;

            for(var a in audio){
                audioHTML+=
                    '<tr extension="'+audio[a].extension+'">'+
                        '<td>'+iter+'</th>'+
                        '<td>'+(audio[a].file_size == 'Unknown'?audio[a].file_size:audio[a].file_size+' MB')+'</td>'+
                        '<td>'+audio[a].bitrate+' KB/s</td>'+
                        '<td>'+audio[a].extension+'</td>'+
                        '<td>'+audio[a].codec+'</td>'+
                        '<td><input type="radio" data="audio" name="audio_selected_format" value="'+audio[a].format_id+'"></td>'+
                    '</tr>';
                iter++;
            }

            return audioHTML;
        },
        generateVideoTable:function(video){

            var videoHTML = '',
                iterV     = 1;

            for(var a in video){
                //singleVideoFormats
                videoHTML+=
                    '<tr extension="'+video[a].extension+'">'+
                        '<td>'+iterV+'</td>'+
                        '<td>'+(video[a].file_size == 'Unknown'?video[a].file_size:video[a].file_size+' MB')+'</td>'+
                        '<td>'+video[a].resolution+'</td>'+
                        '<td>'+video[a].bitrate+' KB/s</td>'+
                        '<td>'+video[a].extension+'</td>'+
                        '<td><input type="radio" data="video" name="video_selected_format" value="'+video[a].format_id+'"></td>'+
                    '</tr>';
                iterV++;
            }

            return videoHTML;
        },
        displaySingleVideoInfo:function(data,url){

            $('.singleVideoThumbnail').attr('src',data.available[0].thumbnail);
            $('.singleVideoTitle').text(data.available[0].title);

            $('.downloadSingle').attr('video_url',url);

            var audio = data.available[0].formats.audio,
                video = data.available[0].formats.video;

            var audioHTML = this.generateAudioTable(audio),
                videoHTML = this.generateVideoTable(video);


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

            var _this = this;
            var response = this.ajax(
                'post',
                'json',
                function(data){
                    try{

                        if(params.playlist_id != false){
                            _this.displayPlaylistInfo(data);
                        }else{
                            _this.displaySingleVideoInfo(data,params.url);
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
        setPlaylistFormatSelection(youtube_id){

            var item = this.playlist[youtube_id];

            $('.playlistItemPreviewData h6').text(item.title);
            $('.playlistItemPreviewData img').attr('src',item.thumbnail);

            var video_formats = item.formats.video,
                audio_formats = item.formats.audio;

            var audioHTML = this.generateAudioTable(audio_formats),
                videoHTML = this.generateVideoTable(video_formats);

            $('.playlistFormatSelection').attr('youtube_id',youtube_id);

            $('.singleAudioFormatsPlaylist').html(audioHTML);
            $('.singleVideoFormatsPlaylist').html(videoHTML);

        },
        resetSelections:function(){

            $('.selectedAudio,.selectedVideo').removeClass('selectedAudio').removeClass('selectedVideo');

            $('.formatsBox').find('tr').show();
            $('.formatsBox').find('input').removeAttr('checked');

        },
        revertSingleFormatSelection:function(revertAll){

            var attributes = ['video_codec_id','audio_codec_id','video_desc','audio_desc'];


            var youtube_id  = $('.playlistFormatSelection').attr('youtube_id'),
                selector    = '.ytbPlaylistItem[youtube_id="'+youtube_id+'"]';

                $('.playlistFormatSelection').find('.playlistTable').addClass('disabledBox');

                $('.singleAudioFormatsPlaylist').find('tr').removeClass('selectedAudio');
                $('.singleVideoFormatsPlaylist').find('tr').removeClass('selectedVideo');

                $('.disabledBox').find('input').removeAttr('checked').prop('checked',false);

                $('#customRadioInline1').attr('checked','checked').prop('checked',true);
                $('#customRadioInline2').removeAttr('checked').prop('checked',false);

            if(typeof revertAll != 'undefined' && revertAll == true){
                selector = '.ytbPlaylistItem';
            }

            for(var attribute of attributes){
                $(selector).removeAttr(attribute);
            }

            $(selector).attr('method','default');

        },
        events:function(){

            var _this = this;

            $('.playlistItemBox').on('change',function(){

                if($(this).val() == 2){
                    $('.saveSelection').removeAttr('disabled');
                    $('.playlistFormatSelection').find('.playlistTable').removeClass('disabledBox');
                }else{
                    $('.saveSelection').attr('disabled','disabled');
                    _this.revertSingleFormatSelection();
                }

            })

            $('.playlistFormatSelection').on('click','.singleAudioFormatsPlaylist tr',function(e){

                if($(this).parentsUntil('.playlistTable').parent().hasClass('disabledBox')){
                    e.stopImmediatePropagation();
                    return false;
                }

                if($(this).hasClass('selectedAudio')){
                    $('.singleAudioFormatsPlaylist').find('input').removeAttr('checked').prop('checked',false);
                    $(this).removeClass('selectedAudio');
                }else{
                    $('.singleAudioFormatsPlaylist').find('tr').removeClass('selectedAudio');
                    $(this).addClass('selectedAudio');
                    $(this).find('input').attr('checked','checked').prop('checked',true);
                }

            });

            $('.playlistFormatSelection').on('click','.singleVideoFormatsPlaylist tr',function(e){

                if($(this).parentsUntil('.playlistTable').parent().hasClass('disabledBox')){
                    e.stopImmediatePropagation();
                    return false;
                }

                if($(this).hasClass('selectedVideo')){
                    $('.singleVideoFormatsPlaylist').find('input').removeAttr('checked').prop('checked',false);
                    $(this).removeClass('selectedVideo');
                }else{
                    $('.singleVideoFormatsPlaylist').find('tr').removeClass('selectedVideo');
                    $(this).addClass('selectedVideo');
                    $(this).find('input').attr('checked','checked').prop('checked',true);
                }

            })

            $('.downloadSelectedVideos').on('click',function(){

                var amount_selected = $('.ytbPlaylistItem').find('input').filter(':checked').length,
                    selected_vids   = {};

                if(amount_selected){

                    $('.ytbPlaylistItem').each(function(){

                        if($(this).find('input').is(":checked")){

                            amount_selected++;

                            var download_method  =  $(this).attr('method'),
                                video_id         =  $(this).attr('youtube_id'),
                                audio_stream     =  $(this).attr('audio_stream'),
                                video_stream     =  $(this).attr('video_stream');

                            selected_vids[download_method] = {
                                'method'    :   download_method,
                                'audio'     :   audio_stream,
                                'video'     :   video_stream
                            }

                        }

                    })

                }else{
                    alertify.alert('Error','Please select at least one video');
                    return false;
                }

            })

            $('input[name="format_method"]').on('change',function(){

                //reset previous selections
                _this.resetSelections();

                $('.formatsBox')[$(this).val() == 1?'addClass':'removeClass']('disabledBox');

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

            $('.playlistBox').on('click','.ytbPlaylistItem input',function(){

                var youtube_id = $(this).parentsUntil('.ytbPlaylistItem').parent().attr('youtube_id');

                    _this.setPlaylistFormatSelection(youtube_id);

                    if($(this).is(":checked")){
                        //set overflow hidden temporarily for body content to prevent of displaying 2 scroll bars simultaneously
                        $('body').css('overflow','hidden');
                        $('.playlistFormatSelection').fadeIn();
                    }else{
                        if($('.playlistFormatSelection').is(":visible")){
                            $('.playlistFormatSelection').fadeOut();
                            $('body').removeAttr('style');
                        }
                    };

            })

            $('.closeSelection').on('click',function(){
                $('.playlistFormatSelection').fadeOut();
                _this.revertSingleFormatSelection();
                $('body').removeAttr('style');
            })

            $('.saveSelection').on('click',function(e){

                $('body').removeAttr('style');

                var selected_video = $('.singleVideoFormatsPlaylist tr.selectedVideo'),
                    selected_audio = $('.singleAudioFormatsPlaylist tr.selectedAudio');

                var youtube_id     = $('.playlistFormatSelection').attr('youtube_id'),
                    video_id       = false,
                    audio_id       = false,
                    video_desc     = '',
                    audio_desc     = '';

                if(selected_video.length){
                    video_id = selected_video.find('input').val();

                    var desc        = selected_video.children('td'),
                        description = desc.eq(1).text()+' @ '+desc.eq(2).text()+' @ '+desc.eq(3).text()+' @ '+desc.eq(4).text();

                    video_desc = description;
                };

                if(selected_audio.length){
                    audio_id = selected_audio.find('input').val();

                    var desc = selected_audio.children('td');
                        description = desc.eq(1).text()+' @ '+desc.eq(2).text()+' @ '+desc.eq(3).text()+' @ '+desc.eq(4).text();

                    audio_desc = description;
                };

                if(selected_audio.length == 0 && selected_video.length == 0){

                    alertify.alert('Error','Please select at least one audio / video format, both audio and video or use default option');

                    e.stopImmediatePropagation();

                    return false;

                }else{
                    $('.playlistFormatSelection').fadeOut();
                }

                var params = {
                    'video_codec_id'   :  video_id,
                    'audio_codec_id'   :  audio_id,
                    'video_desc'       :  video_desc,
                    'audio_desc'       :  audio_desc,
                    'method'           :  'manual'
                };

                $('.ytbPlaylistItem[youtube_id="'+youtube_id+'"]').attr(params);

            })

            $('.selectAllVideos,.undoAllVideos').on('click',function(){

                var playlist    =   $('.playlistBox').find('input').removeAttr('checked');

                    $('.selectAllVideos,.undoAllVideos').find('i').removeClass('fas fa-check-square').addClass('far fa-square');

                    if($(this).hasClass('selectAllVideos')){
                        _this.revertSingleFormatSelection(true);
                        playlist.attr('checked','checked').prop('checked',true);
                    }else{
                        playlist.prop('checked',false);
                    }

                    $(this).find('i').toggleClass('fas far').toggleClass('fa-check-square fa-square');

            })


        },
    }

    return _public;
}())