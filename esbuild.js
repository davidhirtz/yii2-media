import * as esbuild from 'esbuild'

const isWatch = process.argv.slice(2).includes('--watch');
let startTime;

let context = await esbuild.context({
    entryPoints: [
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

                build.onEnd((result) => !result.errors.length
                    ? console.info(`Compiled scripts with esbuild (${esbuild.version}) in ${Date.now() - startTime}ms`)
                    : console.error(result.errors));
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