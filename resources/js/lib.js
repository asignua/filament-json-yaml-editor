import { load } from 'js-yaml'

/**
 * Pure helpers shared by the editor component and the tree view.
 * Nothing here touches the DOM, so node can test it (tests-js/).
 */

/** Offset of the 1-based line/column inside the text. */
export function offsetOf(text, line, column) {
    const lines = text.split('\n')
    let offset = 0

    for (let i = 0; i < Math.min(line - 1, lines.length); i++) {
        offset += lines[i].length + 1
    }

    return Math.min(offset + Math.max(column - 1, 0), text.length)
}

export function lineOf(text, offset) {
    return text.slice(0, offset).split('\n').length
}

export function isBlank(text) {
    return typeof text !== 'string' || text.trim() === ''
}

/**
 * Offset of the first syntax error of a JSON text, or -1 when it is valid. The engines
 * disagree on whether (and how) JSON.parse reports a position, so this is the fallback.
 */
export function jsonErrorOffset(text) {
    let i = 0
    const fail = () => {
        throw i
    }
    const ws = () => {
        while (i < text.length && ' \t\n\r'.includes(text[i])) {
            i++
        }
    }
    const literal = (word) => {
        if (text.startsWith(word, i)) {
            i += word.length
        } else {
            fail()
        }
    }
    const string = () => {
        i++
        while (i < text.length && text[i] !== '"') {
            if (text[i] === '\\') {
                i++
            } else if (text[i] < ' ') {
                fail()
            }
            i++
        }
        if (i >= text.length) {
            fail()
        }
        i++
    }
    const value = () => {
        ws()
        const c = text[i]
        if (c === '{') {
            i++
            ws()
            if (text[i] === '}') {
                i++
                return
            }
            for (;;) {
                ws()
                if (text[i] !== '"') {
                    fail()
                }
                string()
                ws()
                if (text[i] !== ':') {
                    fail()
                }
                i++
                value()
                ws()
                if (text[i] === ',') {
                    i++
                } else if (text[i] === '}') {
                    i++
                    return
                } else {
                    fail()
                }
            }
        } else if (c === '[') {
            i++
            ws()
            if (text[i] === ']') {
                i++
                return
            }
            for (;;) {
                value()
                ws()
                if (text[i] === ',') {
                    i++
                } else if (text[i] === ']') {
                    i++
                    return
                } else {
                    fail()
                }
            }
        } else if (c === '"') {
            string()
        } else if (c === 't') {
            literal('true')
        } else if (c === 'f') {
            literal('false')
        } else if (c === 'n') {
            literal('null')
        } else {
            const match = /^-?(0|[1-9]\d*)(\.\d+)?([eE][+-]?\d+)?/.exec(text.slice(i))
            if (!match) {
                fail()
            }
            i += match[0].length
        }
    }

    try {
        value()
        ws()

        return i < text.length ? i : -1
    } catch (offset) {
        if (typeof offset !== 'number') {
            throw offset
        }

        return offset
    }
}

/**
 * @returns {{ok: true, value: any} | {ok: false, message: string, from: number, line: number}}
 */
export function parseJson(text) {
    try {
        return { ok: true, value: JSON.parse(text) }
    } catch (error) {
        const message = String(error.message ?? error)
        let from = 0

        const byPosition = message.match(/position (\d+)/i)
        const byLine = message.match(/line (\d+) column (\d+)/i)

        if (byPosition) {
            from = Math.min(Number(byPosition[1]), text.length)
        } else if (byLine) {
            from = offsetOf(text, Number(byLine[1]), Number(byLine[2]))
        } else {
            from = Math.max(jsonErrorOffset(text), 0)
        }

        return { ok: false, message, from, line: lineOf(text, from) }
    }
}

export function parseYaml(text) {
    try {
        return { ok: true, value: load(text) }
    } catch (error) {
        const from = Math.min(error.mark?.position ?? 0, text.length)

        return {
            ok: false,
            message: String(error.reason ?? error.message ?? error),
            from,
            line: (error.mark?.line ?? lineOf(text, from) - 1) + 1,
        }
    }
}

export function formatJson(text, indent = 2) {
    const result = parseJson(text)

    return result.ok ? JSON.stringify(result.value, null, indent) : null
}

/* ---------------------------------------------------------------- tree */

export function typeOf(value) {
    if (value === null) {
        return 'null'
    }

    if (Array.isArray(value)) {
        return 'array'
    }

    return typeof value
}

export function getAt(root, path) {
    return path.reduce((node, key) => node[key], root)
}

export function defaultFor(type) {
    return {
        string: '',
        number: 0,
        boolean: false,
        null: null,
        object: {},
        array: [],
    }[type]
}

/** Converts a typed-in string into the value of the given scalar type. */
export function coerce(raw, type) {
    if (type === 'number') {
        const number = Number(raw)

        return raw.trim() === '' || Number.isNaN(number) ? null : number
    }

    if (type === 'boolean') {
        return raw === true || raw === 'true'
    }

    if (type === 'null') {
        return null
    }

    return String(raw)
}

export function setAt(root, path, value) {
    if (path.length === 0) {
        return value
    }

    getAt(root, path.slice(0, -1))[path[path.length - 1]] = value

    return root
}

export function removeAt(root, path) {
    if (path.length === 0) {
        return root
    }

    const parent = getAt(root, path.slice(0, -1))
    const key = path[path.length - 1]

    if (Array.isArray(parent)) {
        parent.splice(key, 1)
    } else {
        delete parent[key]
    }

    return root
}

/** Renames a key keeping its position. Returns false on empty or duplicate names. */
export function renameKey(root, path, newKey) {
    const parent = getAt(root, path.slice(0, -1))
    const oldKey = path[path.length - 1]

    if (Array.isArray(parent) || newKey === '' || (newKey !== oldKey && newKey in parent)) {
        return false
    }

    const entries = Object.entries(parent).map(([key, value]) => [key === oldKey ? newKey : key, value])

    Object.keys(parent).forEach((key) => delete parent[key])
    entries.forEach(([key, value]) => (parent[key] = value))

    return true
}

/** Adds an empty child to an object (unique key) or an array. Returns the new key. */
export function addChild(root, path, type = 'string') {
    const node = getAt(root, path)

    if (Array.isArray(node)) {
        node.push(defaultFor(type))

        return node.length - 1
    }

    let key = 'key'
    let counter = 1

    while (key in node) {
        key = `key${++counter}`
    }

    node[key] = defaultFor(type)

    return key
}

export function changeType(root, path, type) {
    return setAt(root, path, defaultFor(type))
}
