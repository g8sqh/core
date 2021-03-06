"use strict"

var gulp = require('gulp');
var h = require('gulp-helpers');

gulp.task('sass', function () {
   h.sass('main.scss', 'main.css');
});

gulp.task('js', function (cb) {
   h.js('**/*.js', 'main.js', cb);
});

gulp.task('watch', function () {
    gulp.watch(h.paths.sass + '**/*.scss', ['sass']);
    gulp.watch(h.paths.js + '**/*.js', ['js']);
});

gulp.task('default', ['sass', 'js'])
