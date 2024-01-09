import * as esbuild from 'esbuild'

// Use --watch flag to watch for changes and rebuild automatically.
const isWatch = process.argv.slice(2).includes('--watch');

let context = await esbuild.context({
    entryPoints: ['src/assets/admin/js/admin.js'],
    minify: true,
    outfile: 'src/assets/admin/js/admin.min.js',
    sourcemap: true,
    target: 'es5',
})

if (isWatch) {
    await context.watch();
} else {
    await context.rebuild();
    await context.dispose();
}