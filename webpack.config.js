const path = require('path');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const ZipPlugin = require('zip-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

try {
  require('dotenv').config({ path: path.resolve(__dirname, '.env') });
} catch (e) {}

const PLUGIN_NAME = 'brixlab-assistant';
const PLUGIN_VERSION = require('./package.json').version;

function makeCopyPlugin(toDir, { isProduction = false } = {}) {
  const prodApiBase = (process.env.BRIXLAB_ASSISTANT_API_BASE_PRODUCTION || '').replace(/\/+$/, '');
  return new CopyWebpackPlugin({
    patterns: [
      // Main plugin PHP
      {
        from: path.resolve(__dirname, 'brixlab-assistant.php'),
        to: path.join(toDir, 'brixlab-assistant.php'),
        transform: (content) => {
          let result = content.toString();
          if (isProduction && prodApiBase) {
            result = result.replace(
              /define\('BRIXLAB_ASSISTANT_API_BASE',\s*defined\('BRIXTE_API_BASE'\)\s*\?\s*BRIXTE_API_BASE\s*:\s*'[^']*'\)/,
              `define('BRIXLAB_ASSISTANT_API_BASE', defined('BRIXTE_API_BASE') ? BRIXTE_API_BASE : '${prodApiBase}')`
            );
          }
          result = result.replace(
            /define\('BRIXLAB_ASSISTANT_VERSION',\s*'[^']*'\)/,
            `define('BRIXLAB_ASSISTANT_VERSION', '${PLUGIN_VERSION}')`
          );
          result = result.replace(/\* Version:\s*\S+/, `* Version: ${PLUGIN_VERSION}`);
          return result;
        },
      },
      // PHP source
      { from: path.resolve(__dirname, 'src'), to: path.join(toDir, 'src') },
      // Views
      { from: path.resolve(__dirname, 'views'), to: path.join(toDir, 'views') },
      // CSS
      { from: path.resolve(__dirname, 'assets/css'), to: path.join(toDir, 'assets/css') },
      // Static JS (license settings)
      { from: path.resolve(__dirname, 'assets/js/license-settings.js'), to: path.join(toDir, 'assets/js/license-settings.js') },
    ],
  });
}

const prodTerser = new TerserPlugin({
  extractComments: false,
  terserOptions: {
    compress: { drop_console: true, drop_debugger: true },
    format: { comments: false },
  },
});

module.exports = (env = {}, argv = {}) => {
  const isDevMode = argv.mode === 'development' || process.env.NODE_ENV === 'development';
  const isWatch = !!argv.watch;

  const DEV_ROOT = path.resolve(__dirname, 'dist', 'Develop', PLUGIN_NAME);
  const PROD_ROOT = path.resolve(__dirname, 'dist', 'Production', PLUGIN_NAME);

  const commonConfig = {
    entry: {
      assistant: path.resolve(__dirname, 'assets/ts/assistant/index.tsx'),
    },
    output: {
      filename: 'assets/js/[name].js',
      libraryTarget: 'window',
    },
    resolve: { extensions: ['.tsx', '.ts', '.js'] },
    externals: { jquery: 'jQuery' },
    module: {
      rules: [
        { test: /\.(ts|tsx)$/, use: 'ts-loader', exclude: /node_modules/ },
      ],
    },
    optimization: { splitChunks: false, runtimeChunk: false },
  };

  const devConfig = {
    ...commonConfig,
    name: 'develop',
    mode: 'development',
    devtool: 'source-map',
    output: {
      ...commonConfig.output,
      path: DEV_ROOT,
    },
    optimization: { ...commonConfig.optimization, minimize: false },
    watchOptions: { ignored: ['**/dist/**', '**/*.zip'] },
    module: {
      rules: [{
        test: /\.(ts|tsx)$/, exclude: /node_modules/,
        use: { loader: 'ts-loader', options: { compilerOptions: { sourceMap: true, inlineSources: true } } },
      }],
    },
    plugins: [
      new CleanWebpackPlugin(),
      makeCopyPlugin(DEV_ROOT),
    ],
  };

  const prodConfig = {
    ...commonConfig,
    name: 'production',
    mode: 'production',
    devtool: false,
    output: {
      ...commonConfig.output,
      path: PROD_ROOT,
    },
    optimization: {
      ...commonConfig.optimization,
      minimize: true,
      minimizer: [prodTerser],
    },
    plugins: [
      new CleanWebpackPlugin(),
      makeCopyPlugin(PROD_ROOT, { isProduction: true }),
      new ZipPlugin({
        filename: `${PLUGIN_NAME}.zip`,
        pathPrefix: PLUGIN_NAME,
        path: path.resolve(__dirname, 'dist', 'Production'),
      }),
    ],
  };

  if (isWatch || isDevMode) {
    return devConfig;
  }

  return [devConfig, prodConfig];
};
