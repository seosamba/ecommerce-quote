define(['./views/app'],
    function(AppView) {
        return {
            initialize: function() {
                window.appView = new AppView();
            }
        };
    }
);