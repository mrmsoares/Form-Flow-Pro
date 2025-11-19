const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: {
            // Admin scripts
            'admin': './src/admin/index.js',
            'submissions': './src/admin/submissions.js',
            'analytics': './src/admin/analytics.js',
            'settings': './src/admin/settings.js',

            // Admin styles
            'admin-style': './src/scss/admin.scss',
            'critical-style': './src/scss/critical.scss',
        },

        output: {
            path: path.resolve(__dirname, 'assets'),
            filename: 'js/[name].min.js',
            clean: true,
        },

        module: {
            rules: [
                // JavaScript
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env'],
                        },
                    },
                },

                // SCSS/CSS
                {
                    test: /\.(scss|css)$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        'css-loader',
                        {
                            loader: 'postcss-loader',
                            options: {
                                postcssOptions: {
                                    plugins: [
                                        ['postcss-preset-env', {}],
                                    ],
                                },
                            },
                        },
                        'sass-loader',
                    ],
                },

                // Images
                {
                    test: /\.(png|jpg|jpeg|gif|svg)$/,
                    type: 'asset/resource',
                    generator: {
                        filename: 'images/[name][ext]',
                    },
                },

                // Fonts
                {
                    test: /\.(woff|woff2|eot|ttf|otf)$/,
                    type: 'asset/resource',
                    generator: {
                        filename: 'fonts/[name][ext]',
                    },
                },
            ],
        },

        plugins: [
            new MiniCssExtractPlugin({
                filename: 'css/[name].min.css',
            }),
        ],

        optimization: {
            minimize: isProduction,
            minimizer: [
                new TerserPlugin({
                    terserOptions: {
                        compress: {
                            drop_console: isProduction,
                            drop_debugger: isProduction,
                        },
                    },
                }),
                new CssMinimizerPlugin(),
            ],
            splitChunks: {
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendor',
                        chunks: 'all',
                    },
                },
            },
        },

        devtool: isProduction ? false : 'source-map',

        performance: {
            hints: isProduction ? 'warning' : false,
            maxEntrypointSize: 500000, // 500 KB
            maxAssetSize: 300000, // 300 KB
        },

        stats: {
            colors: true,
            modules: false,
            children: false,
            chunks: false,
            chunkModules: false,
        },
    };
};
