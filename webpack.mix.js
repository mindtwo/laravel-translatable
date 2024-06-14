let mix = require('laravel-mix');

require('./resources/js/nova-mix');

mix
  .setPublicPath('dist')
  .js('resources/js/field.js', 'js')
  .vue({ version: 3 })
//   .css('resources/css/field.css', 'css')
  .nova('mindtwo/translatable-field')
