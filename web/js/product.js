require.config({
    shim: {
        'libs/underscore/underscore': {
            exports: '_'
        },
        'libs/backbone/backbone' : {
            deps: ['libs/underscore/underscore'],
            exports: 'Backbone'
        }
    }
});

require(['modules/product/application'],
    function(App){
        App.initialize();
    }
);