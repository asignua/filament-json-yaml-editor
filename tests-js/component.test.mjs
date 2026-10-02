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

// The shape Livewire 4's `$entangle()` gives the factory: an Alpine interceptor, not the value.
function entangled(initialValue) {
    return {
        initialValue,
        _x_interceptor: true,
        initialize(data, path, key) {
            return initialValue
        },
    }
}

function make(language, state, extra = {}) {
    const component = factory({
        language, modes: language === 'json' ? ['code', 'tree'] : ['code'], mode: 'code', isDisabled: false,
        isLive: false, isLiveDebounced: false, isLiveOnBlur: false, label: 'x', liveDebounce: 0, canWrap: false,
        indent: 2, labels, state: entangled(state), ...extra,
    })
    // What Alpine does with interceptors in data before init(): `$wire.$entangle()` returns one.
    for (const [key, value] of Object.entries(component)) {
        if (value && typeof value === 'object' && value._x_interceptor) {
            component[key] = value.initialize(component, key, key)
        }
    }
    component.$refs = { editor: w.document.getElementById('editor'), tree: w.document.getElementById('tree') }
    component.commits = 0
    component.$wire = { $commit: () => component.commits++ }
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

test('json: expanding a tree node leaves the text alone and sends nothing', () => {
    const text = '{"a":{"b":1.0},"big":12345678901234567890}'
    const c = make('json', text, { isLive: true })

    c.setView('tree')
    w.document.querySelectorAll('.jye-toggle')[1].click()
    assert.equal(w.document.querySelectorAll('.jye-key').length, 3)
    w.document.querySelectorAll('.jye-toggle')[1].click()

    assert.equal(c.editor.state.doc.toString(), text)
    assert.equal(c.state, text)
    assert.equal(c.isDocChanged, false)
    assert.equal(c.commits, 0)
    c.destroy()
})

test('json: a tree edit on a live field commits once', () => {
    const c = make('json', '{"a": 1}', { isLive: true })

    c.setView('tree')
    const value = w.document.querySelector('.jye-value')
    value.value = '2'
    value.dispatchEvent(new w.Event('change'))

    assert.equal(c.commits, 1)
    c.destroy()
})

test('json: a tree edit on an on-blur field commits when the focus leaves', () => {
    const c = make('json', '{"a": 1}', { isLive: true, isLiveOnBlur: true })

    c.setView('tree')
    const value = w.document.querySelector('.jye-value')
    value.value = '2'
    value.dispatchEvent(new w.Event('change'))
    assert.equal(c.commits, 0)

    value.dispatchEvent(new w.FocusEvent('focusout', { bubbles: true }))
    assert.equal(c.commits, 1)
    c.destroy()
})

test('an array pushed by the server ($set) is shown as text', () => {
    const json = make('json', { a: [1] })
    assert.equal(json.editor.state.doc.toString(), '{\n  "a": [\n    1\n  ]\n}')

    json.state = { b: 2 }
    json.watchers.state()
    json.watchers.state()
    assert.equal(json.editor.state.doc.toString(), '{\n  "b": 2\n}')
    json.destroy()

    const yaml = make('yaml', null)
    yaml.state = { server: { host: 'x' } }
    yaml.watchers.state()
    yaml.watchers.state()
    assert.equal(yaml.editor.state.doc.toString(), 'server:\n  host: x\n')
    yaml.destroy()
})

test('the $entangle interceptor reaches Alpine untouched and the editor shows its value', () => {
    const interceptor = entangled('a: 1\n')
    const component = factory({
        language: 'yaml', modes: ['code'], mode: 'code', isDisabled: false, isLive: false, isLiveDebounced: false,
        isLiveOnBlur: false, label: 'x', liveDebounce: 0, canWrap: false, indent: 2, labels, state: interceptor,
    })
    assert.equal(component.state, interceptor)

    const json = make('json', '{"a": 1}')
    assert.equal(json.editor.state.doc.toString(), '{"a": 1}')
    assert.equal(json.state, '{"a": 1}')
    json.destroy()

    const yaml = make('yaml', 'a: 1\n')
    assert.equal(yaml.editor.state.doc.toString(), 'a: 1\n')
    yaml.destroy()
})
