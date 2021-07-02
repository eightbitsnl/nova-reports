let mix = require('laravel-mix')

mix
  .setPublicPath('dist')
  .js('src/resources/js/tool.js', 'js')
  .sass('src/resources/sass/tool.scss', 'css')
