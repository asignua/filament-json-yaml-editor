import assert from 'node:assert/strict'
import { test } from 'node:test'
import {
    addChild,
    changeType,
    coerce,
    formatJson,
    isBlank,
    parseJson,
    parseYaml,
    removeAt,
    renameKey,
    setAt,
} from '../resources/js/lib.js'

test('parseJson returns the value or the error line', () => {
    assert.deepEqual(parseJson('{"a": [1, 2]}'), { ok: true, value: { a: [1, 2] } })

    const bad = parseJson('{\n  "a": 1,\n  "b": }')

    assert.equal(bad.ok, false)
    assert.equal(bad.line, 3)
    assert.ok(bad.message.length > 0)
})

test('parseYaml returns the value or the error line', () => {
    assert.deepEqual(parseYaml('a: 1\nb:\n  - x\n'), { ok: true, value: { a: 1, b: ['x'] } })

    const bad = parseYaml('a: 1\nb: [1, 2\nc: 3')

    assert.equal(bad.ok, false)
    assert.ok(bad.line >= 2)
})

test('formatJson pretty-prints valid JSON and refuses invalid', () => {
    assert.equal(formatJson('{"a":1}', 2), '{\n  "a": 1\n}')
    assert.equal(formatJson('{"a":', 2), null)
})

test('formatJson changes only whitespace', () => {
    const text = '{"id": 12345678901234567890, "b": 1.0, "1": 2, "b": 3, "e": {}, "l": [ ], "s": "a\\"b"}'

    assert.equal(
        formatJson(text, 2),
        '{\n  "id": 12345678901234567890,\n  "b": 1.0,\n  "1": 2,\n  "b": 3,\n  "e": {},\n  "l": [],\n  "s": "a\\"b"\n}',
    )
})

test('isBlank', () => {
    assert.equal(isBlank('  \n'), true)
    assert.equal(isBlank(null), true)
    assert.equal(isBlank('{}'), false)
})

test('tree operations', () => {
    let root = { a: 1, list: [1, 2], nested: { x: 'y' } }

    root = setAt(root, ['a'], 5)
    assert.equal(root.a, 5)

    removeAt(root, ['list', 0])
    assert.deepEqual(root.list, [2])

    removeAt(root, ['nested', 'x'])
    assert.deepEqual(root.nested, {})

    assert.equal(renameKey(root, ['a'], 'b'), true)
    assert.deepEqual(Object.keys(root), ['b', 'list', 'nested'])
    assert.equal(renameKey(root, ['b'], 'list'), false)
    assert.equal(renameKey(root, ['b'], ''), false)

    assert.equal(addChild(root, ['nested']), 'key')
    assert.equal(addChild(root, ['nested']), 'key2')
    assert.equal(addChild(root, ['list']), 1)

    root = changeType(root, ['b'], 'array')
    assert.deepEqual(root.b, [])
    assert.equal(changeType(root, [], 'object') instanceof Object, true)
})

test('coerce', () => {
    assert.equal(coerce('12.5', 'number'), 12.5)
    assert.equal(coerce('', 'number'), null)
    assert.equal(coerce('abc', 'number'), null)
    assert.equal(coerce('true', 'boolean'), true)
    assert.equal(coerce(12, 'string'), '12')
})

test('jsonErrorOffset agrees with JSON.parse', async () => {
    const { jsonErrorOffset } = await import('../resources/js/lib.js')

    for (const ok of ['{}', '[1, 2.5e3, "a\\"b", null, true, {"a": []}]', ' "x" ', '-0.5']) {
        assert.equal(jsonErrorOffset(ok), -1, ok)
    }

    for (const bad of ['{"a":', '[1,]', '{a: 1}', '[1 2]', "{'a': 1}", '01', '"unterminated', '{} x']) {
        assert.ok(jsonErrorOffset(bad) >= 0, bad)
        assert.throws(() => JSON.parse(bad), bad)
    }
})

test('renameKey and addChild look at own keys only, and keep an own __proto__ key', () => {
    const root = { a: 1 }
    assert.equal(renameKey(root, ['a'], 'constructor'), true)
    assert.deepEqual(Object.keys(root), ['constructor'])

    const parsed = JSON.parse('{"__proto__": {"x": 1}, "b": 2}')
    assert.equal(renameKey(parsed, ['b'], 'c'), true)
    assert.equal(JSON.stringify(parsed), '{"__proto__":{"x":1},"c":2}')
    assert.equal(Object.getPrototypeOf(parsed), Object.prototype)

    const node = {}
    assert.equal(addChild(node, []), 'key')
})
