require.config({
    deps: ['modules/grid/application'],

    paths: {
        'underscore'         : '../../../shopping/web/js/libs/underscore/underscore-min',
        'backbone'           : '../../../shopping/web/js/libs/backbone/backbone-min',
        'backbone.paginator' : '../../../shopping/web/js/libs/backbone/backbone.paginator.min',
        'text'               : '../../../shopping/web/js/libs/require/text',
        'i18n'               : '../../../shopping/web/js/libs/require/i18n'
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