import {existsSync} from 'node:fs';

// The monorepo's skeleton, or the one Composer installed beside a bundle checked out on its own.
const config = ['../yii2-skeleton/esbuild.config.js', './vendor/davidhirtz/yii2-skeleton/esbuild.config.js']
    .find((path) => existsSync(new URL(path, import.meta.url)));

const {buildScripts} = await import(config);

await buildScripts();
