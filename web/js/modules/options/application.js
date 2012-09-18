define(['underscore', 'backbone', './views/app'],
    function(_, Backbone, AppView){
        return {
            initialize: function() {
                window.appView = new AppView();
            }
        };
    }
);