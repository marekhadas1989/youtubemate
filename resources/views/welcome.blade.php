<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>

    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="_token" content="{{csrf_token()}}" />
    <title>YouTube Mate - download any video or playlist from YouTube</title>

    <!-- Bootstrap core CSS -->
    <link href="/css/bootstrap.css" rel="stylesheet">

    <!-- Custom fonts for this template -->
    <link href="/vendor/fontawesome-free/css/all.min.css" rel="stylesheet">
    <link href="/vendor/simple-line-icons/css/simple-line-icons.css" rel="stylesheet" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Lato:300,400,700,300italic,400italic,700italic" rel="stylesheet" type="text/css">

    <!-- Custom styles for this template -->
    <link href="/css/landing-page.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/css/alertify.min.css">
</head>

<body>

<style>
    .parent {
        display: flex;
        flex-direction: column;
        justify-content: center;
    }
</style>

<div class="parent" style="display:none;position:fixed;width:100%;height:100%;background:white;top:0px;left:0px;z-index:1000;opacity:0.8;text-align:center">
    <div>
        <img  src="/img/spinner.gif">
        <h4 style="color:#CE1617;margin-top:20px">
            Grab yourself a cup of tea, your request is being processed.
        </h4>
        <h6 style="color:#CE1617;margin-top:5px">
            Depends on the amount of videos you are processing it can take a really long while.
        </h6>
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
                        <div class="col-12 col-md-9 mb-2 mb-md-0">
                            <input type="text" class="form-control form-control-lg" placeholder="Paste link here">
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

<!-- Icons Grid -->
<section class="features-icons text-center boxVideos" style="display:none">

    <div class="container">

        <div class="row">
            <div class="col-lg-4">
                <div class="features-icons-item mx-auto mb-5 mb-lg-0 mb-lg-3">
                    <div class="features-icons-icon d-flex">
                        <i class="icon-screen-desktop m-auto text-primary"></i>
                    </div>
                    <h3>Select Video Resulution</h3>
                    <p class="lead mb-0">Select video resolution or download audio only</p>
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
                    <p class="lead mb-0">You are all set and ready to download selected videos/audios</p>
                </div>
            </div>
        </div>
    </div>

    <div class="container singleVideo" style="margin-top:20px;border:dotted 2px orange;padding:20px">
        <div class="row">
            <div class="col-sm-12 col-md-12 col-lg-12">

                <div class="col-sm-12 col-md-12 col-lg-12">

                    <img style="width:inherit" class="singleVideoThumbnail">
                    <h4 class="singleVideoTitle" style="margin-top:10px"></h4>

                </div>


                <div class="row" style="border:dotted 1px #037DFF;padding:10px;margin-top:20px">

                    <div class="col-sm-12 col-md-6 col-lg-6">
                        <h5>Audio Only</h5>
                        <table class="table DataTable">

                            <thead class="thead-dark">
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Weight</th>
                                    <th scope="col">Bitrate</th>
                                    <th scope="col">Extension</th>
                                    <th scope="col">Codec</th>
                                    <th scope="col">Select</th>
                                </tr>
                            </thead>

                            <tbody class="singleAudioFormats"></tbody>
                        </table>
                    </div>

                    <div class="col-sm-12 col-md-6 col-lg-6">
                        <h5>Video & Audio</h5>
                        <table class="table DataTable">

                            <thead class="thead-dark">
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Weight</th>
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
                        <button type="button" class="btn btn-success btn-lg">Download</button>
                    </div>

                </div>

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
                <div class="testimonial-item mx-auto mb-5 mb-lg-0">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/bZ5Ncy7TqWA/hqdefault.jpg?sqp=-oaymwEZCNACELwBSFXyq4qpAwsIARUAAIhCGAFwAQ==&rs=AOn4CLCxGUZh3moNwQG1GELuDnZoJbVDgA">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="testimonial-item mx-auto mb-5 mb-lg-0">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/QY0HcCXYyOk/hqdefault.jpg?sqp=-oaymwEZCNACELwBSFXyq4qpAwsIARUAAIhCGAFwAQ==&rs=AOn4CLAfSL1uVpsDh0OtGlkca4XWeTyhDA">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="testimonial-item mx-auto mb-5 mb-lg-0">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/lq7dJ25Japs/hqdefault.jpg?sqp=-oaymwEZCNACELwBSFXyq4qpAwsIARUAAIhCGAFwAQ==&amp;rs=AOn4CLBiKtXon7-PMoIrsEau2JFOrd9sLg">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="testimonial-item mx-auto mb-5 mb-lg-0">
                    <img class="img-fluid  mb-3" src="https://i.ytimg.com/vi/KkB8KJV_lYY/hqdefault.jpg?sqp=-oaymwEYCKgBEF5IVfKriqkDCwgBFQAAiEIYAXAB&rs=AOn4CLCPvsXYTVKEF606CzpOOh9qsP1lvg">
                    <h5>Margaret E.</h5>
                    <p class="font-weight-light mb-0">"This is fantastic! Thanks so much guys!"</p>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="testimonial-item mx-auto mb-5 mb-lg-0">
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
                        <a href="#">About</a>
                    </li>
                    <li class="list-inline-item">&sdot;</li>
                    <li class="list-inline-item">
                        <a href="#">Contact</a>
                    </li>
                    <li class="list-inline-item">&sdot;</li>
                    <li class="list-inline-item">
                        <a href="#">Terms of Use</a>
                    </li>
                    <li class="list-inline-item">&sdot;</li>
                    <li class="list-inline-item">
                        <a href="#">Privacy Policy</a>
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

<!-- Bootstrap core JavaScript -->
<script src="/js/jquery.js"></script>
<script src="/js/bootstrap.js"></script>
<script src="/js/alertify.min.js"></script>
<script src="/js/datatables.min.js"></script>
<script src="/js/mate.js?v=<?php echo time(); ?>"></script>
<script type="text/javascript">
    $(function(){
        mate.init();
        $.ajaxSetup({
            headers : {
            'X-CSRF-TOKEN' : $('meta[name="_token"]').attr('content')
            }
        });
    })
</script>
</body>

</html>
