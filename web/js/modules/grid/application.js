define(['./views/app'],
    function(AppView) {
        window.appView = new AppView();
        $(function() {
            $(document).trigger('grid:loaded');
        })
    }
);