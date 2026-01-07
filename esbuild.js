import * as esbuild from 'esbuild'

const isWatch = process.argv.slice(2).includes('--watch');
let startTime;

let context = await esbuild.context({
    entryPoints: [
        'resources/assets/src/css/*',
        'resources/assets/src/js/*',
    ],
    bundle: true,
    format: 'esm',
    minify: true,
    outdir: 'resources/assets/dist/',
    plugins: [
        {
            name: 'logger',
            setup(build) {
                build.onStart(() => void (startTime = Date.now()));

                build.onEnd((result) => console.log(
                    !result.errors.length
                        ? `Compiled scripts with esbuild (${esbuild.version}) in ${Date.now() - startTime}ms`
                        : result.errors));
            },
        }
    ],
    sourcemap: true,
    splitting: true,
    target: 'esnext',
})

if (isWatch) {
    await context.watch();
} else {
    await context.rebuild();
    await context.dispose();
}