-include .env

API_PLATFORM_DIR=api-platform
UID?=1000 # Change this in your .env file if you're not UID 1001
DOCKER_RUN=docker run --rm -e UID=${UID} -e ENVIRONMENT=web -v $(CURDIR):/src -w /src soyuka/php-emscripten-builder

all: php-wasm preload install

preload:
	${DOCKER_RUN} python3 /emsdk/upstream/emscripten/tools/file_packager.py ./php-wasm/php-web.data --preload "/src/api-platform" --js-output=./php-wasm/php-web.data.js --no-node --use-preload-cache --exclude '*node_modules*' '*php-wasm*' '*Tests*' '*features*' '*public*' '*/.*'
	${DOCKER_RUN} chown ${UID} ./php-wasm/php-web.data.js ./php-wasm/php-web.data
	sed -e '/Module = Module || {/r./php-wasm/php-web.data.js' php-wasm/php-web.js > src/php-web.js
	cp php-wasm/php-web.data public/

install: php-wasm
	npm install
	cp -r node_modules/monaco-editor public/
	cp -r node_modules/normalize.css/normalize.css public/
	./node_modules/.bin/browserify -t brfs src/index.js -o public/main.js
	cp php-wasm/php-web.wasm public/

php-wasm:
	git clone git@github.com:soyuka/php-wasm php-wasm.build
	cd php-wasm.build
	docker pull soyuka/php-emscripten-builder
	make 
	cd ..
	cp php-wasm.build/build/php-web.js php-wasm.build/build/php-web.wasm php-wasm/

start:
	caddy start

watch-preload:
	find api-platform | entr sh -c 'make preload | head -n 20'

watch:
	watchify -t brfs src/index.js -o public/main.js

