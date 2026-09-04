/* eslint-disable import/no-extraneous-dependencies, import/no-unresolved, global-require */
const path = require('path');
const fs = require('fs');
const TerserPlugin = require('terser-webpack-plugin');
const webpack = require('webpack');

const SRC_PATH = './js/ckeditor5_plugins';

function createConfig(dir) {
  return {
    mode: 'production',
    optimization: {
      minimize: true,
      minimizer: [
        new TerserPlugin({
          terserOptions: {
            format: {
              comments: false,
            },
          },
          test: /\.js(\?.*)?$/i,
          extractComments: false,
        }),
      ],
      moduleIds: 'named',
    },
    entry: {
      path: path.resolve(__dirname, SRC_PATH, dir, 'src/index.js'),
    },
    output: {
      path: path.resolve(__dirname, './js/build'),
      filename: `${dir}.js`,
      library: ['CKEditor5', dir],
      libraryTarget: 'umd',
      libraryExport: 'default',
    },
    plugins: [
      new webpack.DllReferencePlugin({
        manifest: require('./node_modules/ckeditor5/build/ckeditor5-dll.manifest.json'),
        scope: 'ckeditor5/src',
        name: 'CKEditor5.dll',
      }),
    ],
    module: {
      rules: [{ test: /\.svg$/, use: 'raw-loader' }],
    },
  };
}

// One build per plugin directory.
module.exports = fs
  .readdirSync(SRC_PATH)
  .filter((item) => fs.statSync(path.join(SRC_PATH, item)).isDirectory())
  .map(createConfig);
