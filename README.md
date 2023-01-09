# API Platform Playground

## Installation

```
make
```

This will first build [php-wasm](github.com/soyuka/php-wasm). Then it'll build the preload file with the data to addto `public/php-web.data`.

To run a development server use:

```
caddy start
```

## Development

Use these to watch files and run the preload command:

```
make watch-preload
```

Javascript files are built with `browserify`, use this to watch and build:

```
make watch
```
