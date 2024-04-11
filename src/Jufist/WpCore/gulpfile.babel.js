import babel from 'gulp-babel';
import concat from 'gulp-concat';
import del from 'del';
import gulp from 'gulp';
import uglify from 'gulp-uglify';
import browserSync from 'browser-sync';
const webpack = require('webpack')
const webpackConfig = require('./webpack.config.js')
const config = {
	dependreloadfile: "../../themes/bimber-child-theme/src/reload.js"
};

const server = browserSync.create();

const paths = {
  scripts: {
    src: ['src/*.js', 'src/**/*.js'],
    dest: './'
  }
};

const clean = () => del(['dist']);

// Deprecated
function scripts() {
  return gulp.src(paths.scripts.src, { sourcemaps: true })
        .pipe(babel({presets: ['es2015', '@babel/preset-env'] }))
    .pipe(uglify())
    .pipe(concat('modifications.js'))
    .pipe(gulp.dest(paths.scripts.dest));
}

function assets(cb) {
    return new Promise((resolve, reject) => {
        webpack(webpackConfig, (err, stats) => {
            if (err) {
                return reject(err)
            }
            if (stats.hasErrors()) {
                return reject(new Error(stats.compilation))
            }
            resolve()
        })
    })
}

function reload(done) {
  server.reload();
  done();
}

function serve(done) {
    server.init({
        proxy: 'https://frushi.com', // 'dev.site.com' in your example
        port: 5000,
        logLevel: 'debug'
    });
  /*server.init({
    server: {
      baseDir: './'
    }
  });*/
  done();
}

const { src, dest } = require('gulp');
const sass = require('gulp-sass');
function compileSass(done) {
  src('*.scss')
        .pipe(sass({
            outputStyle: 'nested', 
            includePaths: [
                './node_modules/compass-mixins/lib'
            ]
        }).on('error', sass.logError))
  .pipe(dest('./'));
 done();
}

const write = require('write');
const reload2 = (done) => {
	let c = new Date().toDateString();
	write.sync(config.dependreloadfile, c);
	done();
}

const watch = (reload) => {
	gulp.watch(paths.scripts.src, gulp.series(assets, reload));
	gulp.watch("src/*.scss", gulp.series(assets, reload));
	gulp.watch("*.scss", gulp.series(compileSass, reload));
}

const dev = gulp.series(clean, assets/*, scripts deprecated */, compileSass, serve, watch.bind({}, reload));
const depend = gulp.series(clean, assets/*, scripts deprecated */, compileSass, watch.bind({}, reload2));

exports.reload = gulp.series(reload2);
exports.depend = depend;
exports.default = dev;
