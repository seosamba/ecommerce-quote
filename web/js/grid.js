require.config({
    deps: ['modules/grid/application'],

    paths: {
        'underscore'         : '/plugins/shopping/web/js/libs/underscore/underscore-min',
        'backbone'           : '/plugins/shopping/web/js/libs/backbone/backbone-min',
        'backbone.paginator' : '/plugins/shopping/web/js/libs/backbone/backbone.paginator.min',
        'text'               : '/plugins/shopping/web/js/libs/require/text'
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