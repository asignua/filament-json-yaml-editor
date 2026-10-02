import assert from 'node:assert/strict'
import { test } from 'node:test'
import { JSDOM } from 'jsdom'

// A smoke test of the Alpine component with CodeMirror running inside jsdom: it checks that
// the bundle wires up, lints, formats and switches views. Layout-dependent behaviour
// (folding gutters, scrolling) needs a real browser.
const dom = new JSDOM('<!doctype html><html><body><div id="host"><div id="editor"></div><div id="tree"></div></div></body></html>', {
    pretendToBeVisual: true,
})
const w = dom.window
for (const key of ['window', 'document', 'MutationObserver', 'getComputedStyle', 'requestAnimationFrame', 'cancelAnimationFrame', 'Node', 'Element', 'HTMLElement']) {
    Object.defineProperty(globalThis, key, { value: key === 'window' ? w : w[key], configurable: true, writable: true })
}
Object.defineProperty(globalThis, 'navigator', { value: w.navigator, configurable: true })
w.document.createRange = () => ({
    setStart() {},
    setEnd() {},
    getClientRects: () => [],
    getBoundingClientRect: () => ({ left: 0, right: 0, top: 0, bottom: 0, width: 0, height: 0 }),
})
globalThis.Alpine = { debounce: (fn) => fn }

const { default: factory } = await import('../resources/js/json-yaml-editor.js')

const labels = {
    errorAtLine: 'Line :line: :message',
    treeInvalid: 'invalid',
    tree: {
        type: 'Type', value: 'Value', key: 'Key', add: 'Add', remove: 'Remove', expand: 'Expand', collapse: 'Collapse',
        types: { string: 'text', number: 'number', boolean: 'boolean', null: 'null', object: 'object', array: 'array' },
    },
}

function make(language, state, extra = {}) {
    const component = factory({
        language, modes: language === 'json' ? ['code', 'tree'] : ['code'], mode: 'code', isDisabled: false,
        isLive: false, isLiveDebounced: false, isLiveOnBlur: false, label: 'x', liveDebounce: 0, canWrap: false,
        indent: 2, labels, state, ...extra,
    })
    component.$refs = { editor: w.document.getElementById('editor'), tree: w.document.getElementById('tree') }
    component.$wire = { $commit() {} }
    component.watchers = {}
    component.$watch = (key, callback) => (component.watchers[key] = callback)
    component.$nextTick = (fn) => fn()
    component.init()

    return component
}

test('json: lints the error line and formats', () => {
    const c = make('json', '{"a":1,\n"b": }')

    assert.equal(c.error.line, 2)
    assert.match(c.errorText(), /^Line 2: /)
    assert.equal(c.canFormat(), false)

    c.replaceDoc('{"a":1,"b":[1,2]}')
    c.lint(c.editor)
    assert.equal(c.error, null)
    assert.equal(c.canFormat(), true)

    c.format()
    assert.equal(c.editor.state.doc.toString(), '{\n  "a": 1,\n  "b": [\n    1,\n    2\n  ]\n}')
    assert.equal(c.state, c.editor.state.doc.toString())
    c.destroy()
})

test('json: tree view edits write back into the document', () => {
    const c = make('json', '{"a": 1}')

    c.setView('tree')
    assert.equal(c.treeValid, true)

    const value = w.document.querySelector('.jye-value')
    value.value = '7'
    value.dispatchEvent(new w.Event('change'))

    assert.equal(c.state, '{\n  "a": 7\n}')
    c.destroy()
})

test('json: tree is refused for invalid text', () => {
    const c = make('json', '{oops')

    c.setView('tree')
    assert.equal(c.treeValid, false)
    c.destroy()
})

test('yaml: lints with js-yaml', () => {
    const c = make('yaml', 'a: 1\nb: [1, 2\nc: 3\n')

    assert.ok(c.error)
    assert.ok(c.error.line >= 2)

    c.replaceDoc('a: 1\nb: [1, 2]\n')
    c.lint(c.editor)
    assert.equal(c.error, null)
    c.destroy()
})

test('json: a tree edit is not redrawn by the state watcher (the redraw swallows the next click)', () => {
    const c = make('json', '{"a": 1}')

    c.setView('tree')
    const input = w.document.querySelector('.jye-value')
    input.value = '7'
    input.dispatchEvent(new w.Event('change'))

    // the node the user is working in is still in the page
    assert.equal(w.document.querySelector('.jye-value'), input)
    c.watchers.state()
    assert.equal(w.document.querySelector('.jye-value'), input)

    // a text change that did not come from the tree redraws as before
    c.replaceDoc('{"a": 9}')
    c.watchers.state()
    assert.notEqual(w.document.querySelector('.jye-value'), input)
    c.destroy()
})
