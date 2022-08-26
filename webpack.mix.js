let mix = require("laravel-mix");
let tailwindcss = require("tailwindcss");

mix.setPublicPath("dist").js("src/resources/js/field.js", "js").vue().sass("src/resources/sass/field.scss", "css");

mix.setPublicPath("dist")
    .sass("src/resources/sass/webview.scss", "css")
    .options({
        postCss: [tailwindcss("./tailwind.config.js")],
    });
