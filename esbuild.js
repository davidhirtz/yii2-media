import * as esbuild from 'esbuild'

// Use --watch flag to watch for changes and rebuild automatically.
const isWatch = process.argv.slice(2).includes('--watch');
let cssStartTime;

const watchPlugin = {
    name: 'watch-plugin',
    setup(build) {
        build.onStart(() => {
            cssStartTime = Date.now();
        });

        build.onEnd((result) => {
            if (result.errors.length) {
                console.log(result.errors);
            }

            console.log(`Compiled scripts with esbuild (${esbuild.version}) in ${Date.now() - cssStartTime}ms`);
        });
    }
};

let context = await esbuild.context({
    entryPoints: [
        {
            in: 'assets/admin/scss/admin.js',
            out: 'assets/admin/css/admin.min'
        }
    ],
    minify: true,
    outdir: './',
    plugins: [watchPlugin],
    sourcemap: true,
    target: 'es5',
})

if (isWatch) {
    await context.watch();
} else {
    await context.rebuild();
    await context.dispose();
}