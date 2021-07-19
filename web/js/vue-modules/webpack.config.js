var path = require('path');
var webpack = require('webpack');

module.exports = {
    entry: {
        quotecustomparamsconfig: __dirname + "/quote-custom-params-config/"
    },
    output: {
        path: __dirname,
        filename: '[name].js',
        library: '[name]'
    },
    module: {
        rules: [
            {
                test: /\.vue$/,
                loader: 'vue-loader',
                options: {
                    loaders: {}
                    // other vue-loader options go here
                }
            },
            {
                test: /\.js$/,
                loader: 'babel-loader',
                exclude: /node_modules/
            },
            {
                resourceQuery: /blockType=i18n/,
                loader: '@kazupon/vue-i18n-loader'
            },
        ]
    },
    watch: process.env.NODE_ENV === 'development',
    watchOptions: {
        poll: true,
        aggregateTimeout: 100
    },
    resolve: {
        alias: {
            'vue$': 'vue/dist/vue.esm.js'
        }
    },
    devServer: {
        historyApiFallback: true,
        noInfo: true
    },
    performance: {
        hints: false
    },
    devtool: '#eval-source-map'
};


if (process.env.NODE_ENV === 'production') {
    module.exports.devtool = '#source-map';
    // http://vue-loader.vuejs.org/en/workflow/production.html
    module.exports.plugins = (module.exports.plugins || []).concat([
        new webpack.DefinePlugin({
            'process.env': {
                NODE_ENV: '"production"'
            }
        }),
        new webpack.LoaderOptionsPlugin({
            minimize: true
        })
    ])
}
