var gulp = require('gulp');
var concat = require('gulp-concat');  
var uglify = require('gulp-uglify');  
var sass = require('gulp-sass');
var sourcemaps = require('gulp-sourcemaps');
var rename = require('gulp-rename');


var styles = [
	'./src/scss/acf-quickedit.scss'
];
var scripts = [
	'./src/js/acf-quickedit.js',
	'./src/js/thumbnail-col.js'
];


gulp.task('styles',function(){
    return gulp.src( styles )
		.pipe(sourcemaps.init())
        .pipe( sass( { 
        	outputStyle: 'expanded' 
        } ).on('error', sass.logError) )
        .pipe( sourcemaps.write() )
        .pipe( gulp.dest( './css/' ) );
});

gulp.task('styles-build',function(){
    return gulp.src( styles )
        .pipe( sass( { 
			outputStyle: 'compressed', omitSourceMapUrl: true 
        } ).on('error', sass.logError) )
		.pipe( rename( 'acf-quickedit.css') )
		.pipe( gulp.dest('./css/'));
});

gulp.task('scripts-build', function() {
    return gulp.src( scripts )
		.pipe(sourcemaps.init())
		.pipe( uglify() )
	    .pipe( concat('acf-quickedit.min.js') )
        .pipe( sourcemaps.write() )
    	.pipe( gulp.dest( './js/' ) );
});


gulp.task( 'watch', function() {
	gulp.watch('scss/**/*.scss', ['styles'] );
	gulp.watch('js/**/*.js', ['scripts-build'] );
} );

gulp.task( 'build', ['styles-build','scripts-build'] );

gulp.task( 'default', ['build','watch'] );

