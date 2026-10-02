import { addChild, changeType, coerce, removeAt, renameKey, setAt, typeOf } from './lib.js'

const TYPES = ['string', 'number', 'boolean', 'null', 'object', 'array']

function el(tag, attributes = {}, children = []) {
    const node = document.createElement(tag)

    for (const [name, value] of Object.entries(attributes)) {
        if (name === 'class') {
            node.className = value
        } else if (name === 'text') {
            node.textContent = value
        } else if (name.startsWith('on')) {
            node.addEventListener(name.slice(2), value)
        } else if (value !== false && value !== null && value !== undefined) {
            node.setAttribute(name, value === true ? '' : value)
        }
    }

    children.forEach((child) => child && node.append(child))

    return node
}

/**
 * Renders an editable tree into `container`. The model is a plain JS value that is
 * mutated in place; `onChange(root)` is called after every edit (and never on expand /
 * collapse, which only redraws). `expanded` is a Set of serialised paths that survives
 * re-rendering.
 */
export function renderTree({ container, root, readOnly, labels, expanded, onChange }) {
    // `rerender: false` is for edits that already show in the DOM (typing in a value, ticking a checkbox).
    // Rebuilding the tree on `change` replaces the buttons under the cursor, so a click on "+" / "×" that
    // blurred the field (change fires on mousedown) landed on a detached node and was lost.
    const commit = (next, rerender = true) => {
        onChange(next, rerender)
    }

    const key = (path) => JSON.stringify(path)

    // Every path array a live row's handlers close over. Renaming a key rewrites them in place, so the rows
    // below keep working WITHOUT a redraw (a redraw would drop the focus the user just moved to the next field).
    const livePaths = []

    function followRename(path, newKey) {
        const depth = path.length - 1
        const prefix = path.slice(0, depth)
        const old = path[depth]
        const under = (candidate) =>
            candidate.length > depth && candidate[depth] === old && prefix.every((part, index) => candidate[index] === part)

        livePaths.filter(under).forEach((candidate) => (candidate[depth] = newKey))

        for (const stored of [...expanded]) {
            const candidate = JSON.parse(stored)

            if (under(candidate)) {
                expanded.delete(stored)
                candidate[depth] = newKey
                expanded.add(JSON.stringify(candidate))
            }
        }
    }

    function typeSelect(path, value) {
        return el(
            'select',
            {
                class: 'jye-type',
                'aria-label': labels.type,
                disabled: readOnly,
                onchange: (event) => {
                    const next = changeType(root, path, event.target.value)
                    expanded.add(key(path))
                    commit(next)
                },
            },
            TYPES.map((type) =>
                el('option', { value: type, text: labels.types[type] ?? type, selected: type === typeOf(value) }),
            ),
        )
    }

    function scalarInput(path, value) {
        const type = typeOf(value)

        if (type === 'null') {
            return el('span', { class: 'jye-null', text: 'null' })
        }

        if (type === 'boolean') {
            return el('input', {
                type: 'checkbox',
                class: 'jye-bool',
                checked: value,
                disabled: readOnly,
                'aria-label': labels.value,
                onchange: (event) => commit(setAt(root, path, event.target.checked), path.length === 0),
            })
        }

        return el('input', {
            type: 'text',
            class: `jye-value jye-${type}`,
            value: String(value),
            inputmode: type === 'number' ? 'decimal' : null,
            readonly: readOnly,
            'aria-label': labels.value,
            onchange: (event) => {
                const next = coerce(event.target.value, type)

                // A number field that cannot be parsed turns into `null`: that changes the row, so redraw it.
                commit(setAt(root, path, next), path.length === 0 || next === null)
            },
        })
    }

    function node(path, value, name) {
        livePaths.push(path)
        const type = typeOf(value)
        const isContainer = type === 'object' || type === 'array'
        const open = expanded.has(key(path)) || path.length === 0
        const count = isContainer ? Object.keys(value).length : 0

        const head = el('div', { class: 'jye-row' })

        if (isContainer) {
            head.append(
                el('button', {
                    type: 'button',
                    class: 'jye-toggle',
                    'aria-expanded': String(open),
                    'aria-label': open ? labels.collapse : labels.expand,
                    text: open ? '▾' : '▸',
                    // Expanding is not an edit: redraw only, never re-serialise the document or commit.
                    onclick: () => {
                        open ? expanded.delete(key(path)) : expanded.add(key(path))
                        renderTree({ container, root, readOnly, labels, expanded, onChange })
                    },
                }),
            )
        } else {
            head.append(el('span', { class: 'jye-toggle jye-toggle--empty' }))
        }

        if (name !== undefined) {
            head.append(
                typeof name === 'number'
                    ? el('span', { class: 'jye-index', text: String(name) })
                    : el('input', {
                          type: 'text',
                          class: 'jye-key',
                          value: name,
                          readonly: readOnly,
                          'aria-label': labels.key,
                          onchange: (event) => {
                              if (renameKey(root, path, event.target.value)) {
                                  name = event.target.value
                                  followRename(path, name)
                                  commit(root, false)
                              } else {
                                  event.target.value = name
                              }
                          },
                      }),
            )
        }

        if (isContainer) {
            head.append(
                el('span', {
                    class: 'jye-summary',
                    text: type === 'array' ? `[${count}]` : `{${count}}`,
                }),
            )
        } else {
            head.append(scalarInput(path, value))
        }

        if (!readOnly) {
            head.append(typeSelect(path, value))

            if (isContainer) {
                head.append(
                    el('button', {
                        type: 'button',
                        class: 'jye-action',
                        text: '+',
                        title: labels.add,
                        'aria-label': labels.add,
                        onclick: () => {
                            expanded.add(key(path))
                            addChild(root, path)
                            commit(root)
                        },
                    }),
                )
            }

            if (path.length > 0) {
                head.append(
                    el('button', {
                        type: 'button',
                        class: 'jye-action jye-action--danger',
                        text: '×',
                        title: labels.remove,
                        'aria-label': labels.remove,
                        onclick: () => commit(removeAt(root, path)),
                    }),
                )
            }
        }

        const wrapper = el('li', { class: 'jye-node', role: 'treeitem', 'aria-expanded': isContainer ? String(open) : null }, [head])

        if (isContainer && open) {
            wrapper.append(
                el(
                    'ul',
                    { class: 'jye-children', role: 'group' },
                    Object.keys(value).map((child) =>
                        node([...path, type === 'array' ? Number(child) : child], value[child], type === 'array' ? Number(child) : child),
                    ),
                ),
            )
        }

        return wrapper
    }

    container.replaceChildren(el('ul', { class: 'jye-tree', role: 'tree' }, [node([], root)]))
}
