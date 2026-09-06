<template>
    <div v-if="!template" class="px-6 py-16 text-center text-sm text-slate-400">Loading...</div>
    <div v-else class="flex h-[calc(100vh-2rem)] flex-col gap-3">
        <!-- Top bar -->
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 dark:border-slate-800 dark:bg-slate-900">
            <button type="button" class="rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Back" @click="goBack">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            </button>
            <input v-model="template.name" type="text" class="form-input !w-52" @change="pushHistory" />
            <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ categoryLabel }}</span>
            <select class="form-input !w-auto !py-1.5 !text-xs" :value="pagePresetValue" @change="applyPagePreset($event.target.value)">
                <option value="">Page size...</option>
                <option v-for="p in pagePresets" :key="p.label" :value="`${p.width_mm}x${p.height_mm}`">{{ p.label }} ({{ p.width_mm }}×{{ p.height_mm }})</option>
                <option value="custom">Custom (edit mm below)</option>
            </select>
            <span class="text-xs text-slate-400">{{ template.page_width_mm }} × {{ template.page_height_mm }} mm</span>
            <button
                v-if="!template.is_default"
                type="button"
                class="btn-outline !py-1 !text-xs"
                title="Use this layout for all Print / Download of this type"
                @click="makeDefault"
            >
                Set default
            </button>
            <span v-else class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400">Default</span>

            <div class="ml-auto flex items-center gap-1">
                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" title="Undo" :disabled="historyIndex <= 0" @click="undo">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14L4 9l5-5M4 9h10.5a5.5 5.5 0 010 11H11"/></svg>
                </button>
                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 disabled:opacity-30 dark:hover:bg-slate-800" title="Redo" :disabled="historyIndex >= history.length - 1" @click="redo">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M15 14l5-5-5-5M20 9H9.5a5.5 5.5 0 000 11H13"/></svg>
                </button>
                <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" title="Toggle panels" @click="fullscreen = !fullscreen">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4h6M4 4v6M4 4l6 6m10-6h-6m6 0v6m0-6l-6 6M4 20h6m-6 0v-6m0 6l6-6m10 6h-6m6 0v-6m0 6l-6-6"/></svg>
                </button>
                <button type="button" class="btn-outline !py-1.5 inline-flex items-center gap-1.5" @click="openCode">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 9l-4 4 4 4m8-8l4 4-4 4M14 4l-4 16"/></svg>
                    Code
                </button>
                <button type="button" class="btn-outline !py-1.5 inline-flex items-center gap-1.5" :disabled="previewing" @click="previewPdf">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                    {{ previewing ? 'Opening...' : 'Preview PDF' }}
                </button>
                <button type="button" class="btn-primary !py-1.5" :disabled="saving" @click="save">{{ saving ? 'Saving...' : 'Save' }}</button>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 gap-3">
            <!-- Left sidebar -->
            <aside v-if="!fullscreen" class="w-64 shrink-0 overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Data fields</p>
                <p class="mt-1 text-xs text-slate-400">Values are filled automatically on the page where the template is used. Required fields can be hidden but not deleted.</p>
                <div class="mt-3 space-y-1">
                    <div v-for="f in fields" :key="f.key" class="flex items-center gap-1.5 rounded-lg px-2 py-1.5" :class="fieldElement(f.key) ? 'bg-slate-50 dark:bg-slate-800/60' : ''">
                        <svg v-if="f.locked" class="h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-12V7a4 4 0 00-8 0v4h8z"/></svg>
                        <button type="button" class="flex-1 truncate text-left text-sm" :class="fieldElement(f.key) ? 'font-semibold text-slate-800 dark:text-slate-100' : 'text-primary-500'" @click="fieldElement(f.key) ? selectElement(fieldElement(f.key).id) : null">
                            {{ f.label }}
                        </button>
                        <template v-if="fieldElement(f.key)">
                            <button type="button" class="shrink-0 text-slate-400 hover:text-slate-700 dark:hover:text-slate-200" title="Show/hide" @click="toggleHidden(fieldElement(f.key))">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" :fill="fieldElement(f.key).hidden ? 'none' : 'currentColor'" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>
                            </button>
                            <button v-if="!f.locked" type="button" class="shrink-0 text-rose-400 hover:text-rose-600" title="Remove" @click="removeField(fieldElement(f.key))">
                                <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                            </button>
                        </template>
                        <button v-else type="button" class="shrink-0 text-primary-500 hover:text-primary-700" title="Add to canvas" @click="addField(f)">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>
                </div>

                <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Insert</p>
                <div class="mt-2 grid grid-cols-2 gap-1.5">
                    <button type="button" class="btn-outline !justify-start !py-1.5 !text-xs" @click="insertElement('text')">T Text</button>
                    <button type="button" class="btn-outline !justify-start !py-1.5 !text-xs" @click="insertElement('rectangle')">▭ Rectangle</button>
                    <button type="button" class="btn-outline !justify-start !py-1.5 !text-xs" @click="insertElement('ellipse')">◯ Ellipse</button>
                    <button type="button" class="btn-outline !justify-start !py-1.5 !text-xs" @click="insertElement('line')">— Line</button>
                    <button type="button" class="btn-outline !justify-start !py-1.5 !text-xs" @click="insertElement('qrcode')">▦ QR Code</button>
                    <button type="button" class="btn-outline !justify-start !py-1.5 !text-xs" @click="insertElement('image')">🖼 Image</button>
                </div>

                <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Page size</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-slate-400">Width (mm)</label>
                        <input v-model.number="template.page_width_mm" type="number" step="0.1" min="20" class="form-input" @change="pushHistory" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-400">Height (mm)</label>
                        <input v-model.number="template.page_height_mm" type="number" step="0.1" min="20" class="form-input" @change="pushHistory" />
                    </div>
                </div>
                <button type="button" class="btn-outline mt-2 w-full !py-1 !text-xs" @click="swapOrientation">Swap portrait / landscape</button>

                <p class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-400">Page background</p>
                <div class="mt-2 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-slate-600 dark:text-slate-300">Color</label>
                        <input v-model="template.background_color" type="color" class="h-7 w-9 cursor-pointer rounded border border-slate-200" @change="pushHistory" />
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-slate-600 dark:text-slate-300">Image</label>
                        <div>
                            <input ref="bgFileInput" type="file" accept="image/*" class="hidden" @change="uploadBackground" />
                            <button type="button" class="btn-outline !py-1 !text-xs" @click="bgFileInput.click()">Upload</button>
                        </div>
                    </div>
                    <input v-model="bgUrlInput" type="text" class="form-input !text-xs" placeholder="...or paste image URL" @change="applyBgUrl" />
                    <p v-if="template.background_image_path && template.background_image_path.startsWith('http')" class="text-[11px] text-amber-600">Pasted URLs preview here but won't appear in the exported PDF — upload a file for that.</p>
                </div>
            </aside>

            <!-- Canvas -->
            <div class="relative min-w-0 flex-1 overflow-auto rounded-xl border border-slate-200 bg-slate-100 dark:border-slate-800 dark:bg-slate-950" @mousedown.self="selectedId = null">
                <div v-if="template.render_mode === 'html'" class="absolute inset-x-0 top-0 z-10 flex items-center justify-between gap-3 border-b border-amber-200 bg-amber-50 px-4 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    <span>This template renders from custom HTML now — the canvas below isn't used for output. Edit it via <strong>Code</strong>, or revert to the canvas.</span>
                    <button type="button" class="btn-outline !py-1 !text-xs shrink-0 !border-amber-300" @click="revertToCanvas">Revert to canvas</button>
                </div>
                <div class="flex min-h-full items-center justify-center p-10">
                    <div :style="page" class="relative shadow-lg" @mousedown.self="selectedId = null">
                        <div
                            v-for="el in sortedElements"
                            :key="el.id"
                            :style="{ ...box(el), cursor: 'move', outline: selectedId === el.id ? '1.5px dashed #3b82f6' : 'none' }"
                            @mousedown.stop="startDrag($event, el)"
                        >
                            <div v-if="el.type === 'text'" :style="text(el)" style="pointer-events: none">{{ content(el) }}</div>
                            <img v-else-if="el.type === 'image' && assetSrc(el)" :src="assetSrc(el)" style="width: 100%; height: 100%; object-fit: cover; pointer-events: none" />
                            <div v-else-if="el.type === 'image'" class="flex h-full w-full items-center justify-center border border-dashed border-slate-300 text-[10px] text-slate-400" style="pointer-events: none">{{ el.field_key || 'Image' }}</div>
                            <div v-else-if="el.type === 'qrcode'" class="flex h-full w-full items-center justify-center bg-slate-100 text-[10px] text-slate-400" style="pointer-events: none">QR</div>

                            <template v-if="selectedId === el.id">
                                <div
                                    v-for="corner in corners"
                                    :key="corner"
                                    class="absolute h-2.5 w-2.5 rounded-full border border-white bg-primary-500"
                                    :style="handleStyle(corner)"
                                    @mousedown.stop="startResize($event, el, corner)"
                                />
                                <div class="absolute left-1/2 -top-6 h-4 w-px -translate-x-1/2 bg-primary-400" />
                                <div
                                    class="absolute left-1/2 -top-8 h-3 w-3 -translate-x-1/2 cursor-alias rounded-full border border-white bg-primary-500"
                                    @mousedown.stop="startRotate($event, el)"
                                />
                            </template>
                        </div>
                    </div>
                </div>

                <div class="absolute bottom-3 right-3 flex items-center gap-1 rounded-lg border border-slate-200 bg-white px-1.5 py-1 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="zoom = Math.max(20, zoom - 10)">−</button>
                    <span class="w-10 text-center text-xs text-slate-500">{{ zoom }}%</span>
                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" @click="zoom = Math.min(200, zoom + 10)">+</button>
                    <button type="button" class="rounded p-1 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800" title="Fit" @click="fitZoom">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M4 8V4h4M20 8V4h-4M4 16v4h4m12-4v4h-4"/></svg>
                    </button>
                </div>
            </div>

            <!-- Properties panel -->
            <aside v-if="!fullscreen && selectedElement" class="w-72 shrink-0 overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-900">
                <div class="flex items-center justify-between">
                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ elementTitle }}</p>
                    <span v-if="selectedElement.locked" class="text-xs text-slate-400">Required</span>
                    <button v-else type="button" class="rounded-md p-1 text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-500/10" title="Delete" @click="removeField(selectedElement)">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3M4 7h16"/></svg>
                    </button>
                </div>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Position &amp; Size (mm)</p>
                <div class="mt-2 grid grid-cols-2 gap-2">
                    <div><label class="text-xs text-slate-400">X</label><input v-model.number="selectedElement.x" type="number" step="0.5" class="form-input" @change="pushHistory" /></div>
                    <div><label class="text-xs text-slate-400">Y</label><input v-model.number="selectedElement.y" type="number" step="0.5" class="form-input" @change="pushHistory" /></div>
                    <div><label class="text-xs text-slate-400">Width</label><input v-model.number="selectedElement.width" type="number" step="0.5" class="form-input" @change="pushHistory" /></div>
                    <div><label class="text-xs text-slate-400">Height</label><input v-model.number="selectedElement.height" type="number" step="0.5" class="form-input" @change="pushHistory" /></div>
                </div>
                <div class="mt-2">
                    <label class="text-xs text-slate-400">Rotation (°)</label>
                    <input v-model.number="selectedElement.rotation" type="number" class="form-input" @change="pushHistory" />
                </div>

                <template v-if="selectedElement.type === 'text'">
                    <div class="mt-3">
                        <label class="text-xs text-slate-400">Label prefix</label>
                        <textarea v-model="selectedElement.label_prefix" rows="2" class="form-input" placeholder='e.g. "Roll No: "' @change="pushHistory" />
                        <p class="mt-1 text-[11px] text-slate-400">The prefix is printed before the real value (e.g. "Roll No: 23").</p>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-slate-400">Font</label>
                            <select v-model="selectedElement.font_family" class="form-input" @change="pushHistory">
                                <option value="Helvetica">Helvetica</option>
                                <option value="Times">Times</option>
                                <option value="Courier">Courier</option>
                                <option value="DejaVu Sans">DejaVu Sans</option>
                            </select>
                        </div>
                        <div><label class="text-xs text-slate-400">Size (pt)</label><input v-model.number="selectedElement.font_size" type="number" step="0.5" class="form-input" @change="pushHistory" /></div>
                    </div>
                    <div class="mt-2 flex items-center gap-1.5">
                        <button type="button" class="btn-outline !px-2.5 !py-1.5" :class="selectedElement.bold ? '!bg-slate-800 !text-white' : ''" @click="toggleProp('bold')"><strong>B</strong></button>
                        <button type="button" class="btn-outline !px-2.5 !py-1.5" :class="selectedElement.italic ? '!bg-slate-800 !text-white' : ''" @click="toggleProp('italic')"><em>I</em></button>
                        <button type="button" class="btn-outline !px-2.5 !py-1.5" :class="selectedElement.align === 'left' ? '!bg-slate-800 !text-white' : ''" @click="setAlign('left')">≡</button>
                        <button type="button" class="btn-outline !px-2.5 !py-1.5" :class="selectedElement.align === 'center' ? '!bg-slate-800 !text-white' : ''" @click="setAlign('center')">≣</button>
                        <button type="button" class="btn-outline !px-2.5 !py-1.5" :class="selectedElement.align === 'right' ? '!bg-slate-800 !text-white' : ''" @click="setAlign('right')">≢</button>
                    </div>
                    <div class="mt-2 grid grid-cols-2 gap-2">
                        <div><label class="text-xs text-slate-400">Line height</label><input v-model.number="selectedElement.line_height" type="number" step="0.05" class="form-input" @change="pushHistory" /></div>
                        <div><label class="text-xs text-slate-400">Text color</label><input v-model="selectedElement.text_color" type="color" class="form-input !p-1" @change="pushHistory" /></div>
                    </div>
                </template>

                <template v-if="selectedElement.type === 'image'">
                    <div class="mt-3">
                        <input ref="elFileInput" type="file" accept="image/*" class="hidden" @change="uploadElementImage" />
                        <button type="button" class="btn-outline w-full !text-xs" @click="elFileInput.click()">Upload image</button>
                    </div>
                </template>
                <template v-if="selectedElement.type === 'qrcode'">
                    <div class="mt-3">
                        <label class="text-xs text-slate-400">QR value</label>
                        <input v-model="selectedElement.content" type="text" class="form-input" @change="pushHistory" />
                    </div>
                </template>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Appearance</p>
                <div class="mt-2 space-y-2">
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-slate-600 dark:text-slate-300">Fill</label>
                        <input :value="selectedElement.fill_color || '#ffffff'" type="color" class="h-7 w-9 cursor-pointer rounded border border-slate-200" @input="selectedElement.fill_color = $event.target.value" @change="pushHistory" />
                    </div>
                    <div class="flex items-center justify-between">
                        <label class="text-sm text-slate-600 dark:text-slate-300">Border color</label>
                        <input :value="selectedElement.border_color || '#000000'" type="color" class="h-7 w-9 cursor-pointer rounded border border-slate-200" @input="selectedElement.border_color = $event.target.value" @change="pushHistory" />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div><label class="text-xs text-slate-400">Border (mm)</label><input v-model.number="selectedElement.border_width_mm" type="number" step="0.1" class="form-input" @change="pushHistory" /></div>
                        <div><label class="text-xs text-slate-400">Radius (mm)</label><input v-model.number="selectedElement.border_radius_mm" type="number" step="0.5" class="form-input" @change="pushHistory" /></div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-400">Padding (mm)</label>
                        <input v-model.number="selectedElement.padding_mm" type="number" step="0.5" class="form-input" @change="pushHistory" />
                    </div>
                    <div>
                        <label class="text-xs text-slate-400">Opacity — {{ selectedElement.opacity }}%</label>
                        <input v-model.number="selectedElement.opacity" type="range" min="0" max="100" class="w-full" @change="pushHistory" />
                    </div>
                </div>

                <p class="mt-4 text-xs font-semibold uppercase tracking-wide text-slate-400">Layer</p>
                <div class="mt-2 flex items-center gap-1.5">
                    <button type="button" class="btn-outline !p-2" title="Send to back" @click="reorder('back')">⇤</button>
                    <button type="button" class="btn-outline !p-2" title="Move backward" @click="reorder('down')">↓</button>
                    <button type="button" class="btn-outline !p-2" title="Move forward" @click="reorder('up')">↑</button>
                    <button type="button" class="btn-outline !p-2" title="Bring to front" @click="reorder('front')">⇥</button>
                </div>
            </aside>
        </div>

        <!-- Code panel: view/copy/download generated markup, or paste/upload custom HTML to replace it -->
        <div v-if="codeOpen" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/40 p-4">
            <div class="flex h-[85vh] w-full max-w-4xl flex-col rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                <div class="flex items-start justify-between border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 dark:text-slate-100">Template code</h2>
                        <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                            Current mode: <strong>{{ codeRenderMode === 'html' ? 'Custom HTML' : 'Canvas' }}</strong> —
                            {{ codeRenderMode === 'html' ? 'this is the exact markup Dompdf renders.' : 'this is the markup the canvas above generates.' }}
                        </p>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800" @click="codeOpen = false">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>

                <div class="rounded-lg mx-5 mt-4 border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    This is rendered by Dompdf, not a browser — it does not run Tailwind's build step or any JS. Utility class names with no matching CSS rule won't render.
                    Paste either inline <code>style="..."</code> attributes or a full <code>&lt;style&gt;</code> block with real CSS rules (e.g. Tailwind's own compiled output). Use double-curly-brace tokens (field name inside, no spaces) for dynamic values — see the Data fields list on the left for what's available.
                </div>

                <div class="min-h-0 flex-1 space-y-2 overflow-y-auto px-5 py-4">
                    <div v-if="codeLoading" class="py-16 text-center text-sm text-slate-400">Loading...</div>
                    <textarea
                        v-else
                        v-model="codeText"
                        spellcheck="false"
                        class="h-full min-h-[40vh] w-full rounded-lg border border-slate-200 bg-slate-50 p-3 font-mono text-xs text-slate-800 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                    ></textarea>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="btn-outline !text-xs" @click="copyCode">{{ copied ? 'Copied!' : 'Copy' }}</button>
                        <button type="button" class="btn-outline !text-xs" @click="downloadCode">Download .html</button>
                        <input ref="codeFileInput" type="file" accept=".html,text/html" class="hidden" @change="uploadCodeFile" />
                        <button type="button" class="btn-outline !text-xs" @click="codeFileInput.click()">Upload .html file</button>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn-outline !text-xs" @click="codeOpen = false">Close</button>
                        <button type="button" class="btn-primary !text-xs" :disabled="codeSaving" @click="saveCode">{{ codeSaving ? 'Saving...' : 'Save as this template' }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import client from '../../api/client';
import { assetUrl, elementBoxStyle, pageStyle, PX_PER_MM, substituteTokens, textStyle } from '../../utils/templateRender';
import { openAndDownloadPdfBlob } from '../../utils/documentPdf';
import { pushToast } from '../../utils/toast';

const route = useRoute();
const router = useRouter();
const templateId = route.params.id;

const template = ref(null);
const fields = ref([]);
const sample = ref({});
const pagePresets = ref([]);
const selectedId = ref(null);
const zoom = ref(60);
const fullscreen = ref(false);
const saving = ref(false);
const previewing = ref(false);
const bgFileInput = ref(null);
const elFileInput = ref(null);
const bgUrlInput = ref('');
const corners = ['tl', 'tr', 'bl', 'br'];

const codeOpen = ref(false);
const codeLoading = ref(false);
const codeSaving = ref(false);
const codeText = ref('');
const codeRenderMode = ref('canvas');
const codeFileInput = ref(null);
const copied = ref(false);

const history = ref([]);
const historyIndex = ref(-1);

async function load() {
    const { data } = await client.get(`/documents/templates/${templateId}`);
    template.value = data;
    const [fieldsRes, sampleRes, presetsRes] = await Promise.all([
        client.get('/documents/templates/fields', { params: { category: data.category } }),
        client.get('/documents/templates/sample-data', { params: { category: data.category } }),
        client.get('/documents/templates/page-presets'),
    ]);
    fields.value = fieldsRes.data;
    sample.value = sampleRes.data;
    pagePresets.value = presetsRes.data;
    bgUrlInput.value = data.background_image_path && data.background_image_path.startsWith('http') ? data.background_image_path : '';
    history.value = [snapshot()];
    historyIndex.value = 0;
    await nextTick();
    fitZoom();
}
load();

const categoryLabel = computed(() => ({
    admit_card: 'Admit Card', id_card: 'ID Card', transport_card: 'Transport Card', library_card: 'Library Card',
    certificate: 'Certificate', report_card: 'Report Card / Exam Results', exam_schedule: 'Exam Schedule', fee_receipt: 'Fee Receipt', fee_due_receipt: 'Fee Due Receipt',
    salary_slip: 'Salary Slip', book_expense: 'Book Expense',
}[template.value?.category] || template.value?.category));

const pagePresetValue = computed(() => {
    if (!template.value) return '';
    const match = pagePresets.value.find(
        (p) => Math.abs(p.width_mm - template.value.page_width_mm) < 0.15
            && Math.abs(p.height_mm - template.value.page_height_mm) < 0.15
    );
    return match ? `${match.width_mm}x${match.height_mm}` : 'custom';
});

function applyPagePreset(value) {
    if (!value || value === 'custom' || !template.value) return;
    const [w, h] = value.split('x').map(Number);
    if (!w || !h) return;
    template.value.page_width_mm = w;
    template.value.page_height_mm = h;
    pushHistory();
    fitZoom();
}

function swapOrientation() {
    if (!template.value) return;
    const w = template.value.page_width_mm;
    template.value.page_width_mm = template.value.page_height_mm;
    template.value.page_height_mm = w;
    pushHistory();
    fitZoom();
}

async function makeDefault() {
    if (!template.value) return;
    const { data } = await client.patch(`/documents/templates/${template.value.id}/default`);
    template.value = { ...template.value, ...data, is_default: true };
    pushToast('This template is now the default for Print / Download.', 'success');
}

function goBack() {
    router.push('/documents/template-builder');
}

// --- rendering ---
const scale = computed(() => (zoom.value / 100) * PX_PER_MM);
const page = computed(() => pageStyle(template.value, scale.value));
const sortedElements = computed(() => [...(template.value?.elements || [])].filter((e) => !e.hidden).sort((a, b) => a.z_index - b.z_index));
const selectedElement = computed(() => template.value?.elements.find((e) => e.id === selectedId.value) || null);
const elementTitle = computed(() => {
    const el = selectedElement.value;
    if (!el) return '';
    if (el.field_key) return fields.value.find((f) => f.key === el.field_key)?.label || el.field_key;
    return el.type.charAt(0).toUpperCase() + el.type.slice(1);
});

function box(el) {
    return elementBoxStyle(el, scale.value);
}
function text(el) {
    return textStyle(el, scale.value);
}
function content(el) {
    return (el.label_prefix || '') + substituteTokens(el.content, sample.value);
}
function assetSrc(el) {
    return el.image_path ? assetUrl(template.value.id, el.image_path) : null;
}
function fieldElement(key) {
    return template.value?.elements.find((e) => e.field_key === key) || null;
}
function selectElement(id) {
    selectedId.value = id;
}

function fitZoom() {
    if (!template.value) return;
    const container = document.querySelector('.relative.min-w-0.flex-1');
    const available = (container?.clientWidth || 900) - 80;
    const natural = template.value.page_width_mm * PX_PER_MM;
    zoom.value = Math.max(20, Math.min(150, Math.round((available / natural) * 100)));
}

// --- history ---
function snapshot() {
    return JSON.stringify({
        elements: template.value.elements,
        background_color: template.value.background_color,
        background_image_path: template.value.background_image_path,
    });
}
function pushHistory() {
    const snap = snapshot();
    if (history.value[historyIndex.value] === snap) return;
    history.value = history.value.slice(0, historyIndex.value + 1);
    history.value.push(snap);
    historyIndex.value = history.value.length - 1;
}
function restore(snap) {
    const data = JSON.parse(snap);
    template.value.elements = data.elements;
    template.value.background_color = data.background_color;
    template.value.background_image_path = data.background_image_path;
}
function undo() {
    if (historyIndex.value <= 0) return;
    historyIndex.value--;
    restore(history.value[historyIndex.value]);
}
function redo() {
    if (historyIndex.value >= history.value.length - 1) return;
    historyIndex.value++;
    restore(history.value[historyIndex.value]);
}
function onKeydown(e) {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
        e.preventDefault();
        e.shiftKey ? redo() : undo();
    }
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onUnmounted(() => window.removeEventListener('keydown', onKeydown));

// --- data fields ---
function addField(field) {
    // One field per row, top to bottom — the previous grid-packing math (8mm x-step for a
    // 60mm-wide box) stacked every few fields directly on top of each other by default.
    const count = template.value.elements.filter((e) => e.field_key).length;
    const el = {
        id: crypto.randomUUID(), type: 'text', field_key: field.key, locked: false, hidden: false,
        label_prefix: null, content: `{{${field.key}}}`,
        x: 15, y: 15 + count * 10, width: 85, height: 8, rotation: 0,
        font_family: 'Helvetica', font_size: 10, bold: false, italic: false, align: 'left', line_height: 1.25,
        text_color: '#1e293b', fill_color: null, border_color: null, border_width_mm: 0, border_radius_mm: 0,
        padding_mm: 1.5, opacity: 100, z_index: nextZIndex(), image_path: null,
    };
    template.value.elements.push(el);
    selectedId.value = el.id;
    pushHistory();
}
function toggleHidden(el) {
    el.hidden = !el.hidden;
    pushHistory();
}
function removeField(el) {
    template.value.elements = template.value.elements.filter((e) => e.id !== el.id);
    if (selectedId.value === el.id) selectedId.value = null;
    pushHistory();
}

// --- insert ---
function nextZIndex() {
    return 1 + Math.max(0, ...template.value.elements.map((e) => e.z_index || 0));
}
function insertElement(type) {
    // Stagger diagonally so repeated inserts don't all land on the exact same (20, 20) spot.
    const offset = (template.value.elements.length % 8) * 8;
    const base = {
        id: crypto.randomUUID(), type, field_key: null, locked: false, hidden: false, label_prefix: null,
        content: type === 'text' ? 'New text' : type === 'qrcode' ? 'https://example.com' : '',
        x: 20 + offset, y: 20 + offset, rotation: 0, font_family: 'Helvetica', font_size: 10, bold: false, italic: false,
        align: 'left', line_height: 1.25, text_color: '#1e293b', fill_color: null, border_color: '#1e293b',
        border_width_mm: type === 'rectangle' || type === 'ellipse' ? 0.3 : 0, border_radius_mm: 0, padding_mm: 1.5,
        opacity: 100, z_index: nextZIndex(), image_path: null,
    };
    const sizes = { text: [60, 10], rectangle: [40, 25], ellipse: [30, 30], line: [40, 1], qrcode: [25, 25], image: [30, 30] };
    [base.width, base.height] = sizes[type] || [30, 15];
    template.value.elements.push(base);
    selectedId.value = base.id;
    pushHistory();
}

// --- properties ---
function toggleProp(key) {
    selectedElement.value[key] = !selectedElement.value[key];
    pushHistory();
}
function setAlign(align) {
    selectedElement.value.align = align;
    pushHistory();
}
function reorder(direction) {
    const el = selectedElement.value;
    const list = template.value.elements;
    const sorted = [...list].sort((a, b) => a.z_index - b.z_index);
    const idx = sorted.findIndex((e) => e.id === el.id);
    if (direction === 'back') el.z_index = (sorted[0]?.z_index ?? 0) - 1;
    else if (direction === 'front') el.z_index = (sorted[sorted.length - 1]?.z_index ?? 0) + 1;
    else if (direction === 'down' && idx > 0) [el.z_index, sorted[idx - 1].z_index] = [sorted[idx - 1].z_index, el.z_index];
    else if (direction === 'up' && idx < sorted.length - 1) [el.z_index, sorted[idx + 1].z_index] = [sorted[idx + 1].z_index, el.z_index];
    pushHistory();
}

// --- drag / resize / rotate (axis-aligned mouse-delta math; rotation is visual only) ---
function startDrag(event, el) {
    selectedId.value = el.id;
    const startX = event.clientX;
    const startY = event.clientY;
    const originX = el.x;
    const originY = el.y;
    function onMove(e) {
        el.x = round1(originX + (e.clientX - startX) / scale.value);
        el.y = round1(originY + (e.clientY - startY) / scale.value);
    }
    function onUp() {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
        pushHistory();
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function startResize(event, el, corner) {
    const startX = event.clientX;
    const startY = event.clientY;
    const origin = { x: el.x, y: el.y, width: el.width, height: el.height };
    function onMove(e) {
        const dx = (e.clientX - startX) / scale.value;
        const dy = (e.clientY - startY) / scale.value;
        if (corner.includes('r')) el.width = Math.max(4, round1(origin.width + dx));
        if (corner.includes('l')) {
            el.width = Math.max(4, round1(origin.width - dx));
            el.x = round1(origin.x + dx);
        }
        if (corner.includes('b')) el.height = Math.max(4, round1(origin.height + dy));
        if (corner.includes('t')) {
            el.height = Math.max(4, round1(origin.height - dy));
            el.y = round1(origin.y + dy);
        }
    }
    function onUp() {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
        pushHistory();
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function startRotate(event, el) {
    const boxRect = event.currentTarget.parentElement.getBoundingClientRect();
    const centerX = boxRect.left + boxRect.width / 2;
    const centerY = boxRect.top + boxRect.height / 2;
    function onMove(e) {
        const angle = (Math.atan2(e.clientY - centerY, e.clientX - centerX) * 180) / Math.PI + 90;
        el.rotation = Math.round(angle);
    }
    function onUp() {
        window.removeEventListener('mousemove', onMove);
        window.removeEventListener('mouseup', onUp);
        pushHistory();
    }
    window.addEventListener('mousemove', onMove);
    window.addEventListener('mouseup', onUp);
}

function round1(n) {
    return Math.round(n * 10) / 10;
}

function handleStyle(corner) {
    const top = corner.includes('t') ? '-4px' : 'auto';
    const bottom = corner.includes('b') ? '-4px' : 'auto';
    const left = corner.includes('l') ? '-4px' : 'auto';
    const right = corner.includes('r') ? '-4px' : 'auto';
    return { top, bottom, left, right, cursor: corner === 'tl' || corner === 'br' ? 'nwse-resize' : 'nesw-resize' };
}

// --- background ---
async function uploadBackground(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await client.post(`/documents/templates/${template.value.id}/assets`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    template.value.background_image_path = data.path;
    bgUrlInput.value = '';
    pushHistory();
    event.target.value = '';
}
function applyBgUrl() {
    if (!bgUrlInput.value) return;
    template.value.background_image_path = bgUrlInput.value;
    pushHistory();
}

async function uploadElementImage(event) {
    const file = event.target.files?.[0];
    if (!file || !selectedElement.value) return;
    const formData = new FormData();
    formData.append('file', file);
    const { data } = await client.post(`/documents/templates/${template.value.id}/assets`, formData, { headers: { 'Content-Type': 'multipart/form-data' } });
    selectedElement.value.image_path = data.path;
    pushHistory();
    event.target.value = '';
}

// --- save / preview ---
async function save({ silent = false } = {}) {
    saving.value = true;
    try {
        const { data } = await client.put(`/documents/templates/${template.value.id}`, {
            name: template.value.name,
            page_width_mm: template.value.page_width_mm,
            page_height_mm: template.value.page_height_mm,
            background_color: template.value.background_color,
            background_image_path: template.value.background_image_path,
            elements: template.value.elements,
        });
        template.value = data;
        if (!silent) pushToast('Template saved.', 'success');
    } finally {
        saving.value = false;
    }
}

async function previewPdf() {
    previewing.value = true;
    // Open the tab immediately so the browser doesn't block it after await save().
    const tab = window.open('about:blank', '_blank');
    try {
        await save({ silent: true });
        const response = await client.get(`/documents/templates/${template.value.id}/preview-pdf`, { responseType: 'blob' });
        openAndDownloadPdfBlob(response.data, `${template.value.name || 'template'}-preview.pdf`, tab);
    } catch (error) {
        if (tab && !tab.closed) tab.close();
        throw error;
    } finally {
        previewing.value = false;
    }
}

// --- code view / import ---
async function openCode() {
    // Canvas edits must be saved first so the code view reflects what's on the canvas right now.
    if (template.value.render_mode !== 'html') {
        await save({ silent: true });
    }
    codeOpen.value = true;
    codeLoading.value = true;
    copied.value = false;
    try {
        const { data } = await client.get(`/documents/templates/${template.value.id}/code`);
        codeRenderMode.value = data.render_mode;
        codeText.value = data.render_mode === 'html' ? (data.raw_html || '') : data.html;
    } finally {
        codeLoading.value = false;
    }
}

async function copyCode() {
    await navigator.clipboard.writeText(codeText.value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 1500);
}

function downloadCode() {
    const blob = new Blob([codeText.value], { type: 'text/html' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `${template.value.name || 'template'}.html`;
    a.click();
    URL.revokeObjectURL(url);
}

async function saveCode() {
    codeSaving.value = true;
    try {
        const { data } = await client.put(`/documents/templates/${template.value.id}/code`, { html: codeText.value });
        template.value = data;
        codeRenderMode.value = data.render_mode;
        pushToast('Saved — this template now renders from your custom HTML.', 'success');
    } finally {
        codeSaving.value = false;
    }
}

async function uploadCodeFile(event) {
    const file = event.target.files?.[0];
    if (!file) return;
    const text = await file.text();
    codeText.value = text;
    event.target.value = '';
    pushToast('File loaded into the editor — review it, then "Save as this template".', 'info');
}

async function revertToCanvas() {
    if (!window.confirm('Switch this template back to the canvas? Your custom HTML stays saved and can be restored later — the canvas elements above will be used for rendering again.')) return;
    const { data } = await client.patch(`/documents/templates/${template.value.id}/render-mode`, { render_mode: 'canvas' });
    template.value = data;
    pushToast('Reverted to canvas rendering.', 'success');
}
</script>
