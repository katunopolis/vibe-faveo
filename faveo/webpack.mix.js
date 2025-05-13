const mix = require('laravel-mix');
const fs = require('fs');
const path = require('path');

/*
 |--------------------------------------------------------------------------
 | Mix Asset Management
 |--------------------------------------------------------------------------
 |
 | Mix provides a clean, fluent API for defining some Webpack build steps
 | for your Laravel applications. By default, we are compiling the CSS
 | file for the application as well as bundling up all the JS files.
 |
 */

// Create resources directories if they don't exist
const resourcesJsPath = path.resolve(__dirname, 'resources/js');
const resourcesCssPath = path.resolve(__dirname, 'resources/css');

if (!fs.existsSync(resourcesJsPath)) {
    fs.mkdirSync(resourcesJsPath, { recursive: true });
}

if (!fs.existsSync(resourcesCssPath)) {
    fs.mkdirSync(resourcesCssPath, { recursive: true });
}

// Create empty app.js if it doesn't exist
const appJsPath = path.resolve(resourcesJsPath, 'app.js');
if (!fs.existsSync(appJsPath)) {
    fs.writeFileSync(appJsPath, '// Placeholder for app.js');
}

// Create empty app.css if it doesn't exist
const appCssPath = path.resolve(resourcesCssPath, 'app.css');
if (!fs.existsSync(appCssPath)) {
    fs.writeFileSync(appCssPath, '/* Placeholder for app.css */');
}

mix.js('./resources/js/app.js', 'public/js')
    .postCss('./resources/css/app.css', 'public/css', [
        //
    ])
    .options({
        processCssUrls: false
    });
