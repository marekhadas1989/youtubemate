<!-- Bootstrap core JavaScript -->
<script src="/js/popper.min.js"></script>
<script src="/js/jquery.js"></script>
<script src="/js/bootstrap.js"></script>
<script src="/js/alertify.min.js"></script>
<script src="/js/datatables.min.js"></script>


<script src="/js/lity.min.js"></script>
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