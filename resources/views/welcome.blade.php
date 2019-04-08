<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    @include('header')
</head>

<body>


<style>

    .alertify-notifier {
        color: white;
    }

    .youtube_url_lightbox,
    .showFormatSelectionBox,
    .custom-control input,
    .custom-control label,
    .singleAudioFormatsPlaylist tr,
    .singleVideoFormatsPlaylist tr,
    .selectAllVideos,
    .selectAllVideos code,
    .undoAllVideos,
    .undoAllVideos code,
    .ytbPlaylistItem p img,h6,input{
        cursor:pointer !important;
    }

    .bd-callout-info{
        border-left-color: #5bc0de !Important;
    }

    .bd-callout {
        padding: 1.25rem;
        margin-top: 1.25rem;
        margin-bottom: 1.25rem;
        border: 1px solid #eee;
        border-left-width: .25rem;
        border-radius: .25rem;
    }

    .parent{
        position:fixed;
        width:100%;
        height:100%;
        background:white;
        top:0px;
        left:0px;
        z-index:1000;
        opacity:0.8;
        text-align:center
    }

    .center_flexible_box {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .singleAudioFormats tr,
    .singleVideoFormats tr{
        cursor:pointer
    }

    .selectedVideo{
        background:#218838;
        color:white
    }

    .selectedAudio{
        background:#0069D9;
        color:white
    }

    .disabledBox{
        opacity:0.4;
    }

    .playlistFormatSelection{
        display:flex;
        position:fixed;
        width:100%;
        height:100%;
        background:white;
        top:0px;
        left:0%;
        z-index:1000;
        opacity:0.95;
        text-align:center;
        border:dashed 4px #007bff;
    }

    .progres_bar {
        position:fixed;
        top:0px;
        left:0px;
        width:100%;
        height:20px;
        z-index:1001;
    }

    .progress_bar_inner{
        display:none;
        color:white;
        text-align:center;
        opacity:0.8;
        padding:5px;
        width:100%;
        background:#ffc107;
        border-color: #ffc107;
        border-right:none;
        box-shadow: 0 0 0 0.2rem rgba(255,193,7,.5);
    }

</style>

<div class="progress_bar">
    <div class="progress_bar_inner">
        99.99% ( Exploring Space )
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="aboutModal">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">About</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>This project has been made solely for the testing purposes and it will not be developed in future so please use it carefully.</p>
                <p>Any issues found will not be addressed in future.</p>
                <p>This project is intended for private purposes only.</p>
                <p>And damages caused by using it are totally under your responsibility, moreover you should use it only accordingly to the applicable law in your country.</p>
                <p>Any data processed in here will be ephemeral for the purpose of the demo. Data will neither be persisted nor stored for further usage anywhere.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="terms">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Terms of Use</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>This is demo project, please refer to the about section.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal" tabindex="-1" role="dialog" id="privacy">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Privacy Policy</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>This project does not use any cookies or any other related technologies for storing any data about it's users or tracking users in future.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="parent center_flexible_box" style="display:none">
    <div >
        <img  src="/img/spinner.gif">
        <h4 style="color:#CE1617;margin-top:20px">
            Grab yourself a cup of tea, your request is being processed.
        </h4>
        <h6 style="color:#CE1617;margin-top:5px">
            Stay calm, depends on the amount of videos you are processing it can take a really long while.
        </h6>
    </div>
</div>

<div class="center_flexible_box playlistFormatSelection" style="display:none">

        <div class="row">

            <div class="col-sm-12 col-md-12 col-lg-12 " style="margin:20px 0px 20px 0px">
                <div class="playlistItemPreviewData">
                    <img class="img-fluid" src="#"></p>
                    <h6></h6>
                </div>

                <h4 style="color:#CE1617;margin-top:20px">
                    Select video format or use default settings.
                </h4>

                <div class="custom-control custom-radio custom-control-inline">

                    <input type="radio" checked="checked" name="playlist_format_method[]" class="playlistItemBox custom-control-input" value="1" id="playlistItemBox1">
                    <label class="custom-control-label" for="playlistItemBox1">Download default stream</label>

                </div>

                <div class="custom-control custom-radio custom-control-inline">

                    <input type="radio" name="playlist_format_method[]" class="playlistItemBox custom-control-input" value="2" id="playlistItemBox2">
                    <label class="custom-control-label" for="playlistItemBox2">Choose by yourself</label>

                </div>

            </div>

            <div class="row" style="width:95%;margin-left:2%;max-height:400px;overflow-y:scroll;">

                <div class="col-lg-6 playlistTable disabledBox">
                    <h5>Audio Only</h5>
                    <table class="table DataTable">

                        <thead class="thead-dark">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Size</th>
                            <th scope="col">Bitrate</th>
                            <th scope="col">Extension</th>
                            <th scope="col">Codec</th>
                            <th scope="col">Select</th>
                        </tr>
                        </thead>

                        <tbody class="singleAudioFormatsPlaylist">

                        </tbody>
                    </table>
                </div>

                <div class="col-lg-6 playlistTable disabledBox">
                    <h5>Video Only</h5>
                    <table class="table DataTable">

                        <thead class="thead-dark">
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Size</th>
                            <th scope="col">Resolution</th>
                            <th scope="col">Bitrate</th>
                            <th scope="col">Extension</th>
                            <th scope="col">Select</th>
                        </tr>
                        </thead>

                        <tbody class="singleVideoFormatsPlaylist">

                        </tbody>
                    </table>
                </div>

            </div>
        </div>

        <div class="row" style="margin-top:20px">
            <div class="col-sm-12 col-md-12 col-lg-12">
                <button type="button" class="btn btn-danger closeSelection">
                    Close Selection
                </button>
                <button type="button"  class="btn btn-success saveSelection">
                    Save Selection
                </button>
            </div>
        </div>


</div>

<!-- Navigation -->
<nav class="navbar navbar-light bg-light static-top">
    <div class="container">
        <a class="navbar-brand" href="#"><img src="/img/logo.png"></a>
        <a class="btn btn-primary" href="#">Sign In</a>
    </div>
</nav>

<!-- Masthead -->
<header class="masthead text-white text-center">
    <div class="overlay"></div>
    <div class="container">
        <div class="row">
            <div class="col-xl-9 mx-auto">
                <h1 class="mb-5">Download any video, playlist or audio from YouTube.</h1>
            </div>
            <div class="col-md-10 col-lg-8 col-xl-7 mx-auto">
                <form>
                    <div class="form-row">
                        <div class="col-12 col-md-9 mb-2 mb-md-0 urlLink">
                            <input value="https://www.youtube.com/watch?v=LM0ee-BA9Z0&list=RDGMEMYH9CUrFO7CfLJpaD7UR85wVMPVxCwgO98Ek&index=5" type="text" autocomplete="off" class="form-control form-control-lg" placeholder="Paste link here">
                        </div>
                        <div class="col-12 col-md-3">
                            <button type="button" class="btn btn-block btn-lg btn-primary downloadVideo">Download!</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</header>

<section class="features-icons text-center">

    <div class="container">

        <div class="row">
            <div class="col-lg-4">
                <div class="features-icons-item mx-auto mb-5 mb-lg-0 mb-lg-3">
                    <div class="features-icons-icon d-flex">
                        <i class="icon-screen-desktop m-auto text-primary"></i>
                    </div>
                    <h3>Select Format</h3>
                    <p class="lead mb-0">Select video and / or audio stream</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="features-icons-item mx-auto mb-5 mb-lg-0 mb-lg-3">
                    <div class="features-icons-icon d-flex">
                        <i class="icon-layers m-auto text-primary"></i>
                    </div>
                    <h3>Additional features</h3>
                    <p class="lead mb-0">You can select additional features<br> eg. compress all the downloads</p>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="features-icons-item mx-auto mb-0 mb-lg-3">
                    <div class="features-icons-icon d-flex">
                        <i class="icon-check m-auto text-primary"></i>
                    </div>
                    <h3>You are all set</h3>
                    <p class="lead mb-0">You are all set and ready to download selected videos / audios</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!--
############################################
########### SINGLE VIDEO DOWNLOAD BOX#######
############################################
-->

<!-- Icons Grid -->
<section class="features-icons text-center boxVideos" style="display:none">


    <div class="container singleVideo" style="margin-top:20px;border:dotted 2px orange;padding:20px">
        <div class="row">
            <div class="col-sm-12 col-md-12 col-lg-12">

                <div class="col-sm-12 col-md-12 col-lg-12">

                    <img style="width:inherit" class="singleVideoThumbnail">
                    <h4 class="singleVideoTitle" style="margin-top:10px"></h4>

                </div>

                <div class="row" style="border:dotted 1px #037DFF;padding:10px;margin-top:20px">

                    @include('caveats')

                    <div class="col-sm-12 col-md-6 col-lg-6 audioTable formatsBox disabledBox">
                        <h5>Audio Only</h5>
                        <table class="table table-hover DataTable">

                            <thead class="thead-dark">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Size</th>
                                <th scope="col">Bitrate</th>
                                <th scope="col">Extension</th>
                                <th scope="col">Codec</th>
                                <th scope="col">Select</th>
                            </tr>
                            </thead>

                            <tbody class="singleAudioFormats"></tbody>
                        </table>
                    </div>

                    <div class="col-sm-12 col-md-6 col-lg-6 videoTable formatsBox disabledBox">
                        <h5>Video Only</h5>
                        <table class="table table-hover DataTable">

                            <thead class="thead-dark">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Size</th>
                                <th scope="col">Resolution</th>
                                <th scope="col">Bitrate</th>
                                <th scope="col">Extension</th>
                                <th scope="col">Select</th>
                            </tr>
                            </thead>

                            <tbody class="singleVideoFormats"></tbody>
                        </table>
                    </div>
                    <div class="col-md-12">
                        <button type="button" class="btn btn-success btn-lg downloadSingle">Download</button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</section>


<!--
############################################
######## PLAYLIST VIDEO DOWNLOAD BOX########
############################################
-->

<!-- Icons Grid -->
<section class="features-icons text-center">

    <div class="container singleVideo" style="margin-top:20px;border:dotted 2px orange;padding:20px">

        <div class="row">

                <div class="col-sm-12 col-md-12 col-lg-12 " style="margin:20px 0px 20px 0px">

                    <div class="custom-control custom-radio custom-control-inline">

                        <input type="radio" checked="checked" name="quality_method_playlist" class="custom-control-input" value="1" id="playlistRadio1">
                        <label class="custom-control-label" for="playlistRadio1">Default <code class="highlighter-rouge">(medium quality - audio & video)</code></label>

                    </div>

                    <div class="custom-control custom-radio custom-control-inline">

                        <input type="radio" name="quality_method_playlist" class="custom-control-input" value="2" id="playlistRadio2">
                        <label class="custom-control-label" for="playlistRadio2">Audio Only <code class="highlighter-rouge">(medium quality - audio stream only)</code></label>

                    </div>
                    <div class="custom-control custom-radio custom-control-inline">

                        <input type="radio" name="quality_method_playlist" class="custom-control-input" value="3" id="playlistRadio3">
                        <label class="custom-control-label" for="playlistRadio3">Video Only <code class="highlighter-rouge">(medium quality - video stream only)</code></label>

                    </div>
                    <div class="custom-control custom-radio custom-control-inline">

                        <input type="radio" name="quality_method_playlist" class="custom-control-input" value="4" id="playlistRadio4">
                        <label class="custom-control-label" for="playlistRadio4">Manual Choice <code class="highlighter-rouge">(slowest option)</code></label>

                    </div>
                </div>

        </div>

        <div class="row playlistBox" style="border:dotted 1px #037DFF;padding:10px;margin-top:20px">

        </div>

        <div class="row">
            <div class="col-md-12" style="margin-top:20px">
                <button type="button" class="btn btn-success btn-lg downloadSelectedVideos">Download Selected Videos</button>
            </div>
        </div>

    </div>

</section>

<!-- Testimonials -->
<section class="testimonials text-center bg-light">
    <div class="container">
        <h2 class="mb-5">Recently downloaded ...</h2>
        <div class="row">
            <div class="col-lg-3">
                <div class="youtube_url_lightbox testimonial-item mx-auto mb-5 mb-lg-0" youtube_url="https://www.youtube.com/watch?v=oROoI-bYgGQ">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/bZ5Ncy7TqWA/hqdefault.jpg?sqp=-oaymwEZCNACELwBSFXyq4qpAwsIARUAAIhCGAFwAQ==&rs=AOn4CLCxGUZh3moNwQG1GELuDnZoJbVDgA">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="youtube_url_lightbox testimonial-item mx-auto mb-5 mb-lg-0" youtube_url="https://www.youtube.com/watch?v=1lyu1KKwC74">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/QY0HcCXYyOk/hqdefault.jpg?sqp=-oaymwEZCNACELwBSFXyq4qpAwsIARUAAIhCGAFwAQ==&rs=AOn4CLAfSL1uVpsDh0OtGlkca4XWeTyhDA">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="youtube_url_lightbox testimonial-item mx-auto mb-5 mb-lg-0" youtube_url="https://www.youtube.com/watch?v=djV11Xbc914&list=RDQMdPbSog8GRTk&start_radio=1">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/lq7dJ25Japs/hqdefault.jpg?sqp=-oaymwEZCNACELwBSFXyq4qpAwsIARUAAIhCGAFwAQ==&amp;rs=AOn4CLBiKtXon7-PMoIrsEau2JFOrd9sLg">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="youtube_url_lightbox testimonial-item mx-auto mb-5 mb-lg-0" youtube_url="https://www.youtube.com/watch?v=PIb6AZdTr-A&list=RDQMdPbSog8GRTk&index=3">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/KkB8KJV_lYY/hqdefault.jpg?sqp=-oaymwEYCKgBEF5IVfKriqkDCwgBFQAAiEIYAXAB&rs=AOn4CLCPvsXYTVKEF606CzpOOh9qsP1lvg">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="youtube_url_lightbox testimonial-item mx-auto mb-5 mb-lg-0" youtube_url="https://www.youtube.com/watch?v=CdqoNKCCt7A&list=RDQMdPbSog8GRTk&index=4">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/od-U_Zh6FjY/hqdefault.jpg?sqp=-oaymwEZCNACELwBSFXyq4qpAwsIARUAAIhCGAFwAQ==&rs=AOn4CLCl6zDXRRqtFK0HHniLBNgIE18f5g">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
        </div>
    </div>
</section>



<!-- Footer -->
<footer class="footer bg-light">
    <div class="container">
        <div class="row">
            <div class="col-lg-6 h-100 text-center text-lg-left my-auto">
                <ul class="list-inline mb-2">
                    <li class="list-inline-item">
                        <a href="javascript:void(0)" data-toggle="modal" data-target="#aboutModal">About</a>
                    </li>
                    <li class="list-inline-item">&sdot;</li>
                    <li class="list-inline-item">
                        <a href="mailto:biznesowy@gmail.com">Contact</a>
                    </li>
                    <li class="list-inline-item">&sdot;</li>
                    <li class="list-inline-item">
                        <a href="javascript:void(0)" data-toggle="modal" data-target="#terms">Terms of Use</a>
                    </li>
                    <li class="list-inline-item">&sdot;</li>
                    <li class="list-inline-item">
                        <a href="javascript:void(0)" data-toggle="modal" data-target="#privacy">Privacy Policy</a>
                    </li>
                </ul>
                <p class="text-muted small mb-4 mb-lg-0">&copy; YouTube Mate 2019. Copyright All Right.</p>
            </div>
            <div class="col-lg-6 h-100 text-center text-lg-right my-auto">
                <ul class="list-inline mb-0">
                    <li class="list-inline-item mr-3">
                        <a href="#">
                            <i class="fab fa-facebook fa-2x fa-fw"></i>
                        </a>
                    </li>
                    <li class="list-inline-item mr-3">
                        <a href="#">
                            <i class="fab fa-twitter-square fa-2x fa-fw"></i>
                        </a>
                    </li>
                    <li class="list-inline-item">
                        <a href="#">
                            <i class="fab fa-instagram fa-2x fa-fw"></i>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</footer>

@include('footer')
</body>

</html>
