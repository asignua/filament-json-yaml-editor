import assert from 'node:assert/strict'
import { test } from 'node:test'
import { JSDOM } from 'jsdom'

const dom = new JSDOM('<!doctype html><body><div id="c"></div></body>')
globalThis.document = dom.window.document

const { renderTree } = await import('../resources/js/tree.js')

const labels = {
    type: 'Type', value: 'Value', key: 'Key', add: 'Add', remove: 'Remove', expand: 'Expand', collapse: 'Collapse',
    types: { string: 'text', number: 'number', boolean: 'boolean', null: 'null', object: 'object', array: 'array' },
}

function mount(root, readOnly = false) {
    const container = document.getElementById('c')
    const state = { root, expanded: new Set(), changes: [] }
    const draw = () =>
        renderTree({
            container,
            root: state.root,
            readOnly,
            labels,
            expanded: state.expanded,
            onChange: (next) => {
                state.root = next
                state.changes.push(JSON.stringify(next))
                draw()
            },
        })
    draw()

    return { container, state }
}

const change = (input, value) => {
    input.value = value
    input.dispatchEvent(new dom.window.Event('change'))
}

test('renders only the root level until a node is expanded', () => {
    const { container, state } = mount({ a: { b: 1 }, list: [1, 2] })

    assert.equal(container.querySelectorAll('.jye-key').length, 2)
    assert.equal(container.querySelectorAll('.jye-value').length, 0)

    container.querySelectorAll('.jye-toggle')[1].click()
    assert.equal(container.querySelectorAll('.jye-key').length, 3)
    assert.equal(state.expanded.size, 1)
})

test('editing a value, renaming a key, adding and removing', () => {
    const { container, state } = mount({ a: { b: 1 } })

    container.querySelectorAll('.jye-toggle')[1].click() // expand "a"

    change(container.querySelector('.jye-value'), '42')
    assert.deepEqual(state.root, { a: { b: 42 } })

    change(container.querySelectorAll('.jye-key')[1], 'c')
    assert.deepEqual(state.root, { a: { c: 42 } })

    const adds = container.querySelectorAll('.jye-action:not(.jye-action--danger)')
    adds[1].click() // add to "a"
    assert.deepEqual(state.root, { a: { c: 42, key: '' } })

    container.querySelectorAll('.jye-action--danger')[1].click() // remove "c"
    assert.deepEqual(state.root, { a: { key: '' } })
})

test('changing a type resets the value', () => {
    const { container, state } = mount({ a: 'x' })

    const select = container.querySelectorAll('.jye-type')[1]
    select.value = 'array'
    select.dispatchEvent(new dom.window.Event('change'))

    assert.deepEqual(state.root, { a: [] })
})

test('read-only has no controls and inputs are readonly', () => {
    const { container } = mount({ a: 'x' }, true)

    container.querySelector('.jye-toggle')
    assert.equal(container.querySelectorAll('.jye-action').length, 0)
    assert.equal(container.querySelectorAll('.jye-type').length, 0)
})

test('editing a value does not ask for a redraw (it would swallow the next click), structural edits do', () => {
    const container = document.getElementById('c')
    const calls = []
    const root = { name: 'Acme', n: 1 }
    renderTree({
        container, root, readOnly: false, labels, expanded: new Set(),
        onChange: (next, rerender) => calls.push(rerender),
    })

    change(container.querySelector('.jye-value'), 'Acme 2')
    change(container.querySelectorAll('.jye-value')[1], 'oops') // number that cannot be parsed becomes null
    container.querySelector('.jye-action').click() // add

    assert.deepEqual(calls, [false, true, true])
})

test('a renamed row keeps working without a redraw (focus moves on to the next field)', () => {
    const container = document.getElementById('c')
    const root = { a: { b: 1 } }
    const flags = []
    renderTree({
        container, root, readOnly: false, labels, expanded: new Set(), onChange: (next, rerender) => flags.push(rerender),
    })

    container.querySelectorAll('.jye-toggle')[1].click() // expanding redraws on its own, without onChange
    const expanded = new Set([JSON.stringify(['a'])])
    renderTree({ container, root, readOnly: false, labels, expanded, onChange: (next, rerender) => flags.push(rerender) })

    const keys = container.querySelectorAll('.jye-key')
    change(keys[0], 'renamed') // the parent
    change(keys[1], 'inner') // a child, whose path went through the old name
    change(container.querySelector('.jye-value'), '5')

    assert.deepEqual(root, { renamed: { inner: 5 } })
    assert.deepEqual(flags.slice(-3), [false, false, false])
    assert.deepEqual([...expanded], [JSON.stringify(['renamed'])])
})

test('expanding and collapsing redraws without reporting a change', () => {
    const { container, state } = mount({ a: { b: 1 } })

    container.querySelectorAll('.jye-toggle')[1].click()
    assert.equal(container.querySelectorAll('.jye-key').length, 2)
    container.querySelectorAll('.jye-toggle')[1].click()
    assert.equal(container.querySelectorAll('.jye-key').length, 1)

    assert.deepEqual(state.changes, [])
})
