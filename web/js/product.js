require.config({
    paths: {
        'underscore'         : '/plugins/quote/web/js/libs/underscore/underscore',
        'backbone'           : '/plugins/quote/web/js/libs/backbone/backbone',
        'backbone.paginator' : '/plugins/shopping/web/js/libs/backbone/backbone.paginator.min'
    },
    shim: {
        'underscore': {exports: '_'},
        'backbone' : {
            deps: ['underscore'],
            exports: 'Backbone'
        },
        'backbone.paginator': ['backbone']
    }
});

require(['modules/product/application'],
    function(App){
        App.initialize();
    }
);