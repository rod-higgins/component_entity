const path = require('path');
const glob = require('glob');
const webpack = require('webpack');
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const CopyWebpackPlugin = require('copy-webpack-plugin');
const ReactRefreshWebpackPlugin = require('@pmmmwh/react-refresh-webpack-plugin');
const { BundleAnalyzerPlugin } = require('webpack-bundle-analyzer');

const isDevelopment = process.env.NODE_ENV !== 'production';
const shouldAnalyze = process.env.ANALYZE === 'true';

// Auto-discover all component JSX/TSX files
const componentEntries = {};
const componentFiles = glob.sync('./components/**/*.{jsx,tsx}', {
  ignore: [
    '**/node_modules/**',
    '**/*.test.{jsx,tsx}',
    '**/*.spec.{jsx,tsx}',
    '**/*.stories.{jsx,tsx}'
  ]
});

componentFiles.forEach(file => {
  const name = path.basename(file).replace(/\.(jsx|tsx)$/, '');
  const dir = path.dirname(file).split('/').pop();
  componentEntries[`${dir}.${name}`] = file;
});

// Main renderer entry point
const entries = {
  'component-renderer': './js/component-renderer.js',
  'component-registry': './js/component-registry.js',
  ...componentEntries
};

module.exports = {
  mode: isDevelopment ? 'development' : 'production',
  devtool: isDevelopment ? 'eval-source-map' : 'source-map',
  
  entry: entries,
  
  output: {
    path: path.resolve(__dirname, 'dist'),
    filename: 'js/[name].js',
    chunkFilename: 'js/chunks/[name].[contenthash:8].js',
    assetModuleFilename: 'assets/[name].[hash][ext]',
    clean: true,
    library: {
      name: ['Drupal', 'componentEntity', '[name]'],
      type: 'umd',
      export: 'default'
    },
    globalObject: 'this'
  },
  
  resolve: {
    extensions: ['.ts', '.tsx', '.js', '.jsx', '.json'],
    alias: {
      '@components': path.resolve(__dirname, 'components'),
      '@utils': path.resolve(__dirname, 'js/utils'),
      '@types': path.resolve(__dirname, 'types')
    },
    // Ensure we use the same React instance
    modules: [path.resolve(__dirname, 'node_modules'), 'node_modules']
  },
  
  module: {
    rules: [
      // TypeScript and JavaScript
      {
        test: /\.(ts|tsx|js|jsx)$/,
        exclude: /node_modules/,
        use: {
          loader: 'babel-loader',
          options: {
            presets: [
              ['@babel/preset-env', {
                targets: {
                  browsers: ['>0.25%', 'not ie 11', 'not op_mini all']
                },
                modules: false
              }],
              ['@babel/preset-react', {
                runtime: 'automatic'
              }],
              '@babel/preset-typescript'
            ],
            plugins: [
              '@babel/plugin-transform-runtime',
              isDevelopment && require.resolve('react-refresh/babel')
            ].filter(Boolean),
            cacheDirectory: true
          }
        }
      },
      
      // CSS
      {
        test: /\.css$/,
        use: [
          isDevelopment ? 'style-loader' : MiniCssExtractPlugin.loader,
          {
            loader: 'css-loader',
            options: {
              sourceMap: true,
              modules: {
                auto: true,
                localIdentName: isDevelopment
                  ? '[name]__[local]--[hash:base64:5]'
                  : '[hash:base64:8]'
              }
            }
          },
          {
            loader: 'postcss-loader',
            options: {
              sourceMap: true,
              postcssOptions: {
                plugins: [
                  ['autoprefixer'],
                  ['postcss-preset-env', {
                    stage: 3,
                    features: {
                      'nesting-rules': true,
                      'custom-properties': true,
                      'custom-media-queries': true
                    }
                  }]
                ]
              }
            }
          }
        ]
      },
      
      // Images
      {
        test: /\.(png|jpe?g|gif|svg|webp|avif)$/i,
        type: 'asset',
        parser: {
          dataUrlCondition: {
            maxSize: 8 * 1024 // 8kb
          }
        }
      },
      
      // Fonts
      {
        test: /\.(woff|woff2|eot|ttf|otf)$/i,
        type: 'asset/resource',
        generator: {
          filename: 'fonts/[name].[hash][ext]'
        }
      }
    ]
  },
  
  plugins: [
    // Clean dist folder
    new CleanWebpackPlugin({
      cleanOnceBeforeBuildPatterns: ['**/*', '!.gitkeep']
    }),
    
    // Define environment variables
    new webpack.DefinePlugin({
      'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development'),
      '__DEV__': isDevelopment,
      '__PROD__': !isDevelopment
    }),
    
    // Extract CSS to separate files in production
    !isDevelopment && new MiniCssExtractPlugin({
      filename: 'css/[name].css',
      chunkFilename: 'css/chunks/[name].[contenthash:8].css'
    }),
    
    // React refresh for development
    isDevelopment && new ReactRefreshWebpackPlugin({
      overlay: {
        sockIntegration: 'wds'
      }
    }),
    
    // Copy static assets
    new CopyWebpackPlugin({
      patterns: [
        {
          from: 'components/**/*.html.twig',
          to: '[path][name][ext]'
        },
        {
          from: 'components/**/*.component.yml',
          to: '[path][name][ext]'
        }
      ]
    }),
    
    // Bundle analyzer
    shouldAnalyze && new BundleAnalyzerPlugin({
      analyzerMode: 'static',
      reportFilename: '../bundle-report.html',
      openAnalyzer: false,
      generateStatsFile: true,
      statsFilename: '../stats.json'
    }),
    
    // Progress plugin
    new webpack.ProgressPlugin()
  ].filter(Boolean),
  
  optimization: {
    minimize: !isDevelopment,
    minimizer: [
      new TerserPlugin({
        terserOptions: {
          parse: {
            ecma: 2020
          },
          compress: {
            ecma: 5,
            comparisons: false,
            inline: 2,
            drop_console: !isDevelopment,
            drop_debugger: !isDevelopment,
            pure_funcs: !isDevelopment ? ['console.log', 'console.info'] : []
          },
          mangle: {
            safari10: true
          },
          output: {
            ecma: 5,
            comments: false,
            ascii_only: true
          }
        },
        extractComments: false
      }),
      new CssMinimizerPlugin({
        minimizerOptions: {
          preset: [
            'default',
            {
              discardComments: { removeAll: true }
            }
          ]
        }
      })
    ],
    runtimeChunk: {
      name: 'runtime'
    },
    splitChunks: {
      chunks: 'all',
      cacheGroups: {
        vendor: {
          name: 'vendor',
          test: /[\\/]node_modules[\\/]/,
          priority: 10,
          reuseExistingChunk: true
        },
        react: {
          name: 'react-vendor',
          test: /[\\/]node_modules[\\/](react|react-dom|scheduler)[\\/]/,
          priority: 20
        },
        common: {
          name: 'common',
          minChunks: 2,
          priority: 5,
          reuseExistingChunk: true
        }
      }
    }
  },
  
  // Externals - React is loaded from Drupal libraries
  externals: {
    'react': 'React',
    'react-dom': 'ReactDOM',
    'drupal': 'Drupal',
    'drupalSettings': 'drupalSettings',
    'jquery': 'jQuery'
  },
  
  performance: {
    hints: isDevelopment ? false : 'warning',
    maxEntrypointSize: 512000,
    maxAssetSize: 512000
  },
  
  stats: {
    all: false,
    modules: true,
    errors: true,
    warnings: true,
    publicPath: true,
    assets: true,
    cached: false,
    cachedAssets: false,
    children: false,
    chunks: false,
    chunkModules: false,
    chunkOrigins: false,
    depth: false,
    entrypoints: true,
    excludeAssets: /\.(map|txt)$/,
    hash: true,
    performance: true,
    timings: true,
    version: true
  },
  
  devServer: {
    static: {
      directory: path.join(__dirname, 'dist')
    },
    compress: true,
    port: 3000,
    hot: true,
    liveReload: true,
    open: false,
    headers: {
      'Access-Control-Allow-Origin': '*'
    },
    historyApiFallback: true,
    client: {
      logging: 'warn',
      overlay: {
        errors: true,
        warnings: false
      },
      progress: true
    }
  },
  
  cache: {
    type: 'filesystem',
    cacheDirectory: path.resolve(__dirname, '.webpack-cache'),
    buildDependencies: {
      config: [__filename]
    }
  },
  
  watchOptions: {
    ignored: /node_modules/,
    aggregateTimeout: 300,
    poll: false
  }
};