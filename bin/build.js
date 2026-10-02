import * as esbuild from 'esbuild'

const isDev = process.argv.includes('--dev')

await esbuild.build({
    entryPoints: ['./resources/js/json-yaml-editor.js'],
    outfile: './resources/dist/json-yaml-editor.js',
    bundle: true,
    format: 'esm',
    platform: 'browser',
    target: ['es2020'],
    minify: !isDev,
    sourcemap: false,
    legalComments: 'none',
    logLevel: 'info',
})
