import { Compartment, EditorState } from '@codemirror/state'
import { EditorView, keymap } from '@codemirror/view'
import { basicSetup } from 'codemirror'
import { indentWithTab } from '@codemirror/commands'
import { json } from '@codemirror/lang-json'
import { yaml } from '@codemirror/lang-yaml'
import { linter, lintGutter } from '@codemirror/lint'
import { oneDark } from '@codemirror/theme-one-dark'
import { formatJson, isBlank, parseJson, parseYaml, stateToText } from './lib.js'
import { renderTree } from './tree.js'

/**
 * One Alpine component for both fields: `language` is `json` or `yaml`, `modes` lists the
 * views the user can switch between (`code`, and for JSON also `tree`).
 */
export default function jsonYamlEditorFormComponent({
    language,
    modes,
    mode,
    isDisabled,
    isLive,
    isLiveDebounced,
    isLiveOnBlur,
    label,
    liveDebounce,
    canWrap,
    indent,
    labels,
    state,
}) {
    return {
        // The `$entangle` interceptor itself: Alpine resolves it only when it finds it as a data
        // property, so it must not be converted here (see init()).
        state,
        editor: null,
        view: mode,
        error: null,
        treeRoot: null,
        treeValid: true,
        expanded: new Set(),
        themeCompartment: new Compartment(),
        isDocChanged: false,
        isSyncingFromServer: false,
        treeText: null,
        modes,
        labels,
        isDisabled,

        init() {
            const debouncedCommit = Alpine.debounce(() => this.$wire.$commit(), liveDebounce ?? 300)

            // Entangled by now. An array from the server is converted into the state (CodeMirror
            // only takes text); null is shown as an empty document and left alone: writing ''
            // back would mark the form dirty and send a request for live fields on load.
            if (this.state !== null && this.state !== undefined && typeof this.state !== 'string') {
                this.state = stateToText(this.state, language, indent)
            }

            this.editor = new EditorView({
                parent: this.$refs.editor,
                state: EditorState.create({
                    doc: stateToText(this.state, language, indent),
                    extensions: [
                        basicSetup,
                        keymap.of([indentWithTab]),
                        language === 'yaml' ? yaml() : json(),
                        lintGutter(),
                        linter((view) => this.lint(view), { delay: 250 }),
                        ...(label ? [EditorView.contentAttributes.of({ 'aria-label': label })] : []),
                        ...(canWrap ? [EditorView.lineWrapping] : []),
                        EditorState.readOnly.of(isDisabled),
                        EditorView.editable.of(!isDisabled),
                        EditorView.updateListener.of((update) => {
                            // The server's own state shown in the editor is not an edit to send back.
                            if (!update.docChanged || this.isSyncingFromServer) {
                                return
                            }

                            this.isDocChanged = true
                            this.state = update.state.doc.toString()

                            if (!isLiveOnBlur && (isLive || isLiveDebounced)) {
                                debouncedCommit()
                            }
                        }),
                        EditorView.domEventHandlers({
                            blur: () => {
                                if (isLiveOnBlur && this.isDocChanged) {
                                    this.$wire.$commit()
                                }
                            },
                        }),
                        this.themeCompartment.of(this.themeExtensions()),
                    ],
                }),
            })

            // The server (or another tab of the page) replaced the state.
            this.$watch('state', () => {
                // An array from `$set()` / `fill()`: CodeMirror only takes text. Assigning re-runs
                // this watcher with the string. Null is shown as an empty document.
                if (this.state !== null && this.state !== undefined && typeof this.state !== 'string') {
                    this.state = stateToText(this.state, language, indent)

                    return
                }

                const text = stateToText(this.state, language, indent)

                if (this.editor.state.doc.toString() !== text) {
                    this.isSyncingFromServer = true

                    try {
                        this.replaceDoc(text)
                    } finally {
                        this.isSyncingFromServer = false
                    }
                }

                // The tree itself wrote this text and draws (or deliberately does not draw) its own result.
                if (this.view === 'tree' && text !== this.treeText) {
                    this.renderTreeView()
                }
            })

            this.themeObserver = new MutationObserver(() => {
                this.editor.dispatch({ effects: this.themeCompartment.reconfigure(this.themeExtensions()) })
            })
            this.themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] })

            // Tree edits go through the editor's update listener (live / debounced); on-blur fields
            // commit when the focus leaves a tree control, as they do when it leaves the code view.
            this.$refs.tree?.addEventListener('focusout', () => {
                if (isLiveOnBlur && this.isDocChanged) {
                    this.$wire.$commit()
                }
            })

            if (this.view === 'tree') {
                this.$nextTick(() => this.renderTreeView())
            }

            this.updateError(this.editor.state.doc.toString())
        },

        destroy() {
            this.themeObserver?.disconnect()
            this.editor?.destroy()
        },

        themeExtensions() {
            return document.documentElement.classList.contains('dark') ? [oneDark] : []
        },

        parse(text) {
            return language === 'yaml' ? parseYaml(text) : parseJson(text)
        },

        lint(view) {
            const text = view.state.doc.toString()

            if (isBlank(text)) {
                this.error = null

                return []
            }

            const result = this.parse(text)

            if (result.ok) {
                this.error = null

                return []
            }

            let from = Math.min(result.from, text.length)
            const to = Math.min(from + 1, text.length)

            if (to === from) {
                from = Math.max(0, from - 1)
            }

            this.error = { line: view.state.doc.lineAt(Math.min(result.from, text.length)).number, message: result.message }

            return [{ from, to, severity: 'error', message: result.message }]
        },

        // Validation of a doc that is not (yet) in the editor, e.g. before the first lint pass.
        updateError(text) {
            if (isBlank(text)) {
                this.error = null

                return
            }

            const result = this.parse(text)

            this.error = result.ok ? null : { line: result.line, message: result.message }
        },

        errorText() {
            return this.error ? this.labels.errorAtLine.replace(':line', this.error.line).replace(':message', this.error.message) : ''
        },

        replaceDoc(text) {
            this.editor.dispatch({ changes: { from: 0, to: this.editor.state.doc.length, insert: text } })
        },

        setView(next) {
            this.view = next

            if (next === 'tree') {
                this.renderTreeView()
            } else {
                this.$nextTick(() => {
                    this.editor.requestMeasure()
                    this.editor.focus()
                })
            }
        },

        renderTreeView() {
            const text = this.editor.state.doc.toString()

            if (isBlank(text)) {
                this.treeRoot = {}
                this.treeValid = true
            } else {
                const result = parseJson(text)

                this.treeValid = result.ok

                if (result.ok) {
                    this.treeRoot = result.value
                }
            }

            if (!this.treeValid) {
                this.$refs.tree.replaceChildren()

                return
            }

            renderTree({
                container: this.$refs.tree,
                root: this.treeRoot,
                readOnly: isDisabled,
                labels: this.labels.tree,
                expanded: this.expanded,
                onChange: (root, rerender = true) => {
                    this.treeRoot = root

                    const text = JSON.stringify(root, null, indent)

                    this.treeText = text
                    // The update listener commits live / debounced fields; no second request here.
                    this.replaceDoc(text)

                    if (rerender) {
                        this.renderTreeView()
                    }
                },
            })
        },

        format() {
            if (language !== 'json' || isDisabled) {
                return
            }

            const text = formatJson(this.editor.state.doc.toString(), indent)

            if (text !== null) {
                this.replaceDoc(text)
            }
        },

        canFormat() {
            return language === 'json' && !isDisabled && this.error === null
        },
    }
}
