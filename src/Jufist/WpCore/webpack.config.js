const webpack = require('webpack');
const path = require('path');
const nodeExternals = require('webpack-node-externals');
const ExtractTextPlugin = require("extract-text-webpack-plugin");

const config = {
  // set to false because __dirname resolving to / instead of absolute path when
  // built using webpack
  node: {
      __dirname: false
  },
    resolve: {
        //fallback: {
            // fs: false
        //}
    },
  module: {
    rules: [
	    {
                test: /\.twig$/,
                loader: "twig-loader",
                options: {
                    // See options section below
                },
            },
      {
        test: /\.js$/,
        exclude: /node_modules/,
        use: [
		{
          loader: 'babel-loader',
          options: {
              presets: ['@babel/preset-env'],
              plugins: [
		      require('@babel/plugin-proposal-object-rest-spread')]
          }
        }
	]
      },
        {
            test: /\.s?[ac]ss$/i,
            use: [
                'style-loader',
                'css-loader',
		  {
		    loader: "sass-loader",
		    options: {
		      sassOptions: {
			      outputStyle: 'nested',
            includePaths: [
                './node_modules/compass-mixins/lib'
            ]
		      },
		    },
		  }
            ],
        }
    ]
  },
  // set to development to read .env.local variables
  mode: 'development'
};

const serverConfig = Object.assign({}, config, {
  // set target to node to fix build warnings
  target: 'node',
  name: 'server',
  entry: __dirname + '/src/index.js',
  output: {
    path: path.resolve(__dirname + '/dist'),
    filename: 'index.js'
  },
  // webpack-node-externals package used to exclude other packages like express
  // in the final bundle.js
  externals: [nodeExternals()]
});

// widget.js file served from dist/widget
const widgetConfig = Object.assign({}, config, {
  // set target to web for use in browsers
  target: 'web',
  name: 'index',
    // entry: [__dirname + '/src/index.js'],
    entry: ['@babel/polyfill', __dirname + '/src/index.js'],
  output: {
    path: path.resolve(__dirname + '/dist/'),
    filename: 'index.js'
  }
});

module.exports = [widgetConfig];

// module.exports = [widgetConfig, serverConfig];
