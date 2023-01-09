const nodeFs = require('fs')
const loadPhp = require('./php')
const {iframeLoaded} = require('./utils')
const Editor = require('./editor')
const ResponseView = require('./responseView')
const BodyView = require('./bodyView')
const FileTree = require('./fileTree')
const App = require('./app')
const indexCode = nodeFs.readFileSync(`${__dirname}/php/index.php`, 'utf8')

function loadEditorFrame({FS, onDidChangeContent}) {
  return iframeLoaded(document.getElementById('editor'))
    .then((editorFrame) => {
      const {editor, monaco} = editorFrame.contentWindow
      return Editor({
        FS, editor, monaco, onDidChangeContent,
      })
    })
}

function loadResponseFrame({stdout, stderr}) {
  return iframeLoaded(document.getElementById('response'))
    .then((responseFrame) => {
      const {editor, monaco} = responseFrame.contentWindow
      return ResponseView({
        editor, monaco, stdout, stderr,
      })
    })
}

function loadBodyFrame() {
  return iframeLoaded(document.getElementById('body'))
    .then((bodyFrame) => {
      const {editor, monaco} = bodyFrame.contentWindow
      return BodyView({editor, monaco})
    })
}

function preparePersistentStorage({FS: fs}) {
  function cpR(from, to) {
    const files = fs.readdir(from)

    files.forEach((file) => {
      if (file === '.' || file === '..') return
      const src = `${from}/${file}`
      const dest = `${to}/${file}`
      const stat = fs.stat(src)

      // do not override existing data
      try {
        const exists = fs.stat(dest)
        if (exists) return
      } catch (_) {} // eslint-disable-line no-empty

      if (fs.isDir(stat.mode)) {
        fs.mkdir(dest)
        cpR(src, dest)
        return
      }

      const data = fs.readFile(src, {encoding: 'utf8'})
      fs.writeFile(dest, data)
    })
  }

  // Copy our examples to a persisted storage
  return new Promise((resolve, reject) => {
    // fs.mkdir('/src/api-platform/persisted-examples')
    // fs.mount(fs.filesystems.IDBFS, {}, '/src/api-platform/persisted-examples')
    //
    // fs.syncfs(true, () => {
    //   // if synced data doesn't exist copy initial data
    //   cpR('/src/api-platform', '/src/api-platform/persisted-examples')
    //   fs.syncfs((err) => (err ? reject(err) : resolve(fs)))
    // })
  })
}

function runApp() {
  let initialized
  let failed

  new Promise((resolve, reject) => {
    initialized = resolve
    failed = reject

    return loadPhp()
      .then(({
        FS, stdout, stderr, runCode, phpVersion, apiPlatformVersion, reset, ccall
      }) => {
        document.querySelector('[data-php-version]').innerText = `PHP Version: ${phpVersion}`
        document.querySelector('[data-api-platform-version]').innerText = `API Platform Version: ${apiPlatformVersion}`

        const state = {}
        const NUM = 'number'
        const STR = 'string'

        const saveTimeout = () => {
          if (this.timeout) { clearTimeout(this.timeout) }
          this.timeout = setTimeout(() => {
            state.editor.save()
            runExample(exampleSelect.value)
          }, 5000)
        }

        const saveButton = document.getElementById('save')
        const exampleSelect = document.getElementById('example')
        exampleSelect.addEventListener('change', () => {
          runExample(exampleSelect.value, true)
        })

        saveButton.addEventListener('click', (event) => {
          event.preventDefault()
          state.editor.save()
          runExample(exampleSelect.value)
        })

        function runExample(name, loadFile = false) {
          reset()
          loadFile && state.editor.loadFile(`/src/api-platform/src/${name}.php`);
          ccall('pib_run', NUM, [STR], [`?> ${indexCode}; run("${name}");`])
        }

        loadResponseFrame({stdout, stderr})
        .then(() => {
          return loadEditorFrame({FS})
        })
        .then((editor) => {
          state.editor = editor
          state.editor.addEventListener('change', (state) => {
            if (state.changed) {
              saveButton.removeAttribute('disabled')
              saveTimeout()
            } else {
              saveButton.setAttribute('disabled', 'disabled')
            }
          })
          return Promise.resolve()
        })
        .then(() => {
          runExample(exampleSelect.value, true)
        })


        // return Promise.all([
        // runCode(indexCode);

        //   FileTree({FS}),
        //   runCode,
        //   reset,
        //   loadBodyFrame(),
        //   preparePersistentStorage({FS}),
        //   ccall
        // ])
      })
      // .then((dependencies) => App(dependencies))
  })

  return { initialized, failed }
}

const {initialized, failed} = runApp()
initialized(() => console.log('App started')) // eslint-disable-line no-console
failed((err) => console.error('App errored', err))
