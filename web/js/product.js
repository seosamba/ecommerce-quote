require.config({
    paths: {
        'underscore'         : '../../../shopping/web/js/libs/underscore/underscore-min',
        'backbone'           : '../../../shopping/web/js/libs/backbone/backbone-min',
        'backbone.paginator' : '../../../shopping/web/js/libs/backbone/backbone.paginator.min',
        'text'               : '../../../shopping/web/js/libs/require/text'
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