let mix = require('laravel-mix')
let tailwindcss = require("tailwindcss");

require('./nova.mix')

mix.setPublicPath("dist")
    .js("src/resources/js/field.js", "js")
    .vue()
    // .postCss('src/resources/css/field.css', 'css', [require('tailwindcss')])
    .sass("src/resources/sass/field.scss", "css")
    .nova('eightbitsnl/nova-reports');

mix.setPublicPath("dist")
    .sass("src/resources/sass/webview.scss", "css")
    .options({
        postCss: [tailwindcss("./tailwind.config.js")],
    });
