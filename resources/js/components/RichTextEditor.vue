<script setup>
import { ref, watch, onBeforeUnmount, nextTick } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import TextAlign from '@tiptap/extension-text-align';
import { Table, TableRow, TableCell, TableHeader } from '@tiptap/extension-table';
import Placeholder from '@tiptap/extension-placeholder';
import { Button } from '@/components/ui/button';
import { 
    Bold, 
    Italic, 
    Underline as UnderlineIcon, 
    Strikethrough, 
    Heading1, 
    Heading2, 
    Heading3, 
    List, 
    ListOrdered, 
    AlignLeft, 
    AlignCenter, 
    AlignRight, 
    AlignJustify, 
    Quote, 
    Minus, 
    Link2, 
    Unlink, 
    Table as TableIcon, 
    Undo2, 
    Redo2, 
    Code, 
    FileCode, 
    Eraser,
    X,
    Plus,
    Trash2,
    Columns3,
    Rows3
} from 'lucide-vue-next';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    placeholder: {
        type: String,
        default: 'Ketik atau edit isi konten di sini...',
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    minHeight: {
        type: String,
        default: '320px',
    },
});

const emit = defineEmits(['update:modelValue']);

const isSourceMode = ref(false);
const sourceContent = ref(props.modelValue || '');
const isLinkModalOpen = ref(false);
const linkUrl = ref('');

const editor = useEditor({
    content: props.modelValue || '',
    editable: !props.disabled,
    extensions: [
        StarterKit.configure({
            heading: {
                levels: [1, 2, 3],
            },
        }),
        Underline,
        Link.configure({
            openOnClick: false,
            HTMLAttributes: {
                class: 'text-primary underline hover:opacity-80 transition-opacity',
                target: '_blank',
                rel: 'noopener noreferrer',
            },
        }),
        TextAlign.configure({
            types: ['heading', 'paragraph'],
        }),
        Table.configure({
            resizable: true,
            HTMLAttributes: {
                class: 'border-collapse table-auto w-full border border-border my-4 rounded-lg overflow-hidden',
            },
        }),
        TableRow,
        TableHeader.configure({
            HTMLAttributes: {
                class: 'border border-border bg-muted/60 p-2.5 font-bold text-left text-xs',
            },
        }),
        TableCell.configure({
            HTMLAttributes: {
                class: 'border border-border p-2.5 text-xs',
            },
        }),
        Placeholder.configure({
            placeholder: props.placeholder,
        }),
    ],
    onUpdate: ({ editor }) => {
        const html = editor.getHTML();
        sourceContent.value = html;
        emit('update:modelValue', html);
    },
});

// Watch for external modelValue updates (e.g. tab change or reset)
watch(
    () => props.modelValue,
    (newVal) => {
        const currentHtml = editor.value ? editor.value.getHTML() : '';
        if (newVal !== currentHtml) {
            sourceContent.value = newVal || '';
            if (editor.value) {
                editor.value.commands.setContent(newVal || '', false);
            }
        }
    }
);

watch(
    () => props.disabled,
    (disabled) => {
        if (editor.value) {
            editor.value.setEditable(!disabled);
        }
    }
);

onBeforeUnmount(() => {
    editor.value?.destroy();
});

// Toggle between Visual (WYSIWYG) and HTML Source Code mode
const toggleSourceMode = () => {
    if (isSourceMode.value) {
        // Exiting source mode: sync raw HTML back into Tiptap
        if (editor.value) {
            editor.value.commands.setContent(sourceContent.value || '', false);
            emit('update:modelValue', sourceContent.value);
        }
        isSourceMode.value = false;
    } else {
        // Entering source mode: extract HTML from Tiptap
        sourceContent.value = editor.value ? editor.value.getHTML() : props.modelValue;
        isSourceMode.value = true;
    }
};

const onSourceInput = (e) => {
    sourceContent.value = e.target.value;
    emit('update:modelValue', sourceContent.value);
};

// Link Modal handlers
const openLinkModal = () => {
    if (!editor.value) return;
    const previousUrl = editor.value.getAttributes('link').href;
    linkUrl.value = previousUrl || '';
    isLinkModalOpen.value = true;
};

const closeLinkModal = () => {
    isLinkModalOpen.value = false;
    linkUrl.value = '';
};

const applyLink = () => {
    if (!editor.value) return;
    const url = linkUrl.value.trim();
    if (!url) {
        editor.value.chain().focus().extendMarkRange('link').unsetLink().run();
    } else {
        const fullUrl = /^https?:\/\//i.test(url) ? url : `https://${url}`;
        editor.value.chain().focus().extendMarkRange('link').setLink({ href: fullUrl }).run();
    }
    closeLinkModal();
};

const removeLink = () => {
    if (editor.value) {
        editor.value.chain().focus().unsetLink().run();
    }
    closeLinkModal();
};

// Table Helpers
const insertTable = () => {
    editor.value?.chain().focus().insertTable({ rows: 3, cols: 3, withHeaderRow: true }).run();
};
const addColumnAfter = () => editor.value?.chain().focus().addColumnAfter().run();
const deleteColumn = () => editor.value?.chain().focus().deleteColumn().run();
const addRowAfter = () => editor.value?.chain().focus().addRowAfter().run();
const deleteRow = () => editor.value?.chain().focus().deleteRow().run();
const deleteTable = () => editor.value?.chain().focus().deleteTable().run();
</script>

<template>
    <div class="rich-text-editor-container border rounded-2xl bg-card shadow-2xs overflow-hidden transition-all focus-within:ring-2 focus-within:ring-primary/40 focus-within:border-primary">
        <!-- Toolbar Header -->
        <div class="editor-toolbar bg-muted/40 border-b p-2 flex flex-wrap items-center gap-1 select-none">
            <!-- History / Undo Redo -->
            <div class="flex items-center gap-0.5 pr-1.5 border-r border-border/80">
                <button
                    type="button"
                    @click="editor?.chain().focus().undo().run()"
                    :disabled="!editor?.can().undo() || isSourceMode"
                    title="Undo (Ctrl+Z)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 disabled:pointer-events-none transition-colors"
                >
                    <Undo2 class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().redo().run()"
                    :disabled="!editor?.can().redo() || isSourceMode"
                    title="Redo (Ctrl+Y)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 disabled:pointer-events-none transition-colors"
                >
                    <Redo2 class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Headings -->
            <div class="flex items-center gap-0.5 px-1.5 border-r border-border/80">
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleHeading({ level: 1 }).run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground font-bold shadow-2xs': editor?.isActive('heading', { level: 1 }) }"
                    title="Judul Utama (H1)"
                    class="px-2 py-1 rounded-lg text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    H1
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleHeading({ level: 2 }).run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground font-bold shadow-2xs': editor?.isActive('heading', { level: 2 }) }"
                    title="Sub Judul (H2)"
                    class="px-2 py-1 rounded-lg text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    H2
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleHeading({ level: 3 }).run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground font-bold shadow-2xs': editor?.isActive('heading', { level: 3 }) }"
                    title="Sub-sub Judul (H3)"
                    class="px-2 py-1 rounded-lg text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    H3
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().setParagraph().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground font-bold shadow-2xs': editor?.isActive('paragraph') && !editor?.isActive('heading') }"
                    title="Teks Paragraf Biasa"
                    class="px-2 py-1 rounded-lg text-xs font-semibold text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    P
                </button>
            </div>

            <!-- Formatting -->
            <div class="flex items-center gap-0.5 px-1.5 border-r border-border/80">
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleBold().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('bold') }"
                    title="Tebal (Ctrl+B)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Bold class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleItalic().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('italic') }"
                    title="Miring (Ctrl+I)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Italic class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleUnderline().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('underline') }"
                    title="Garis Bawah (Ctrl+U)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <UnderlineIcon class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleStrike().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('strike') }"
                    title="Coret Teks"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Strikethrough class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleCode().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('code') }"
                    title="Kode Format"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Code class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Alignment -->
            <div class="flex items-center gap-0.5 px-1.5 border-r border-border/80">
                <button
                    type="button"
                    @click="editor?.chain().focus().setTextAlign('left').run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive({ textAlign: 'left' }) }"
                    title="Rata Kiri"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <AlignLeft class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().setTextAlign('center').run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive({ textAlign: 'center' }) }"
                    title="Rata Tengah"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <AlignCenter class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().setTextAlign('right').run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive({ textAlign: 'right' }) }"
                    title="Rata Kanan"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <AlignRight class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().setTextAlign('justify').run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive({ textAlign: 'justify' }) }"
                    title="Rata Kiri Kanan"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <AlignJustify class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Lists -->
            <div class="flex items-center gap-0.5 px-1.5 border-r border-border/80">
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleBulletList().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('bulletList') }"
                    title="Daftar Poin (Bullet List)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <List class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleOrderedList().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('orderedList') }"
                    title="Daftar Bernomor (Numbered List)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <ListOrdered class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().toggleBlockquote().run()"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('blockquote') }"
                    title="Kutipan (Blockquote)"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Quote class="h-3.5 w-3.5" />
                </button>
                <button
                    type="button"
                    @click="editor?.chain().focus().setHorizontalRule().run()"
                    :disabled="isSourceMode"
                    title="Garis Pembatas Horisontal"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Minus class="h-3.5 w-3.5" />
                </button>
            </div>

            <!-- Link & Table -->
            <div class="flex items-center gap-0.5 px-1.5 border-r border-border/80">
                <button
                    type="button"
                    @click="openLinkModal"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('link') }"
                    title="Sisipkan Tautan / Link"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Link2 class="h-3.5 w-3.5" />
                </button>
                <button
                    v-if="editor?.isActive('link')"
                    type="button"
                    @click="removeLink"
                    :disabled="isSourceMode"
                    title="Hapus Link"
                    class="p-1.5 rounded-lg text-destructive hover:bg-destructive/10 transition-colors"
                >
                    <Unlink class="h-3.5 w-3.5" />
                </button>

                <!-- Table Insert Button -->
                <button
                    type="button"
                    @click="insertTable"
                    :disabled="isSourceMode"
                    :class="{ 'bg-primary text-primary-foreground shadow-2xs': editor?.isActive('table') }"
                    title="Sisipkan Tabel 3x3"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <TableIcon class="h-3.5 w-3.5" />
                </button>

                <!-- Table In-Context Quick Operations -->
                <template v-if="editor?.isActive('table') && !isSourceMode">
                    <button
                        type="button"
                        @click="addRowAfter"
                        title="Tambah Baris Bawah"
                        class="p-1.5 rounded-lg text-xs text-muted-foreground hover:text-foreground hover:bg-muted/80"
                    >
                        <Rows3 class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        @click="addColumnAfter"
                        title="Tambah Kolom Kanan"
                        class="p-1.5 rounded-lg text-xs text-muted-foreground hover:text-foreground hover:bg-muted/80"
                    >
                        <Columns3 class="h-3.5 w-3.5" />
                    </button>
                    <button
                        type="button"
                        @click="deleteRow"
                        title="Hapus Baris Ini"
                        class="p-1.5 rounded-lg text-xs text-rose-500 hover:bg-rose-500/10"
                    >
                        -Baris
                    </button>
                    <button
                        type="button"
                        @click="deleteColumn"
                        title="Hapus Kolom Ini"
                        class="p-1.5 rounded-lg text-xs text-rose-500 hover:bg-rose-500/10"
                    >
                        -Kolom
                    </button>
                    <button
                        type="button"
                        @click="deleteTable"
                        title="Hapus Seluruh Tabel"
                        class="p-1.5 rounded-lg text-xs text-destructive hover:bg-destructive/10"
                    >
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </template>
            </div>

            <!-- Utilities: Clear Format & Source HTML Mode -->
            <div class="flex items-center gap-1 ml-auto">
                <button
                    type="button"
                    @click="editor?.chain().focus().clearNodes().unsetAllMarks().run()"
                    :disabled="isSourceMode"
                    title="Bersihkan Semua Format"
                    class="p-1.5 rounded-lg text-muted-foreground hover:text-foreground hover:bg-muted/80 disabled:opacity-30 transition-colors"
                >
                    <Eraser class="h-3.5 w-3.5" />
                </button>

                <button
                    type="button"
                    @click="toggleSourceMode"
                    :class="isSourceMode ? 'bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 font-bold' : 'text-muted-foreground hover:text-foreground hover:bg-muted/80'"
                    class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs transition-colors"
                    title="Beralih ke Tampilan Kode HTML Mentah"
                >
                    <FileCode class="h-3.5 w-3.5" />
                    <span>{{ isSourceMode ? 'Tampilan Visual' : 'Source HTML' }}</span>
                </button>
            </div>
        </div>

        <!-- Content Area -->
        <div class="editor-body p-4 sm:p-5 relative" :style="{ minHeight: minHeight }">
            <!-- Source Code View Mode -->
            <div v-if="isSourceMode" class="w-full">
                <div class="mb-2 flex items-center justify-between text-[11px] text-muted-foreground font-semibold">
                    <span>Mengedit Kode HTML Mentah</span>
                    <span>Klik "Tampilan Visual" untuk kembali</span>
                </div>
                <textarea
                    :value="sourceContent"
                    @input="onSourceInput"
                    :style="{ minHeight: minHeight }"
                    class="w-full font-mono text-xs bg-muted/20 border border-input rounded-xl p-3 leading-relaxed focus:outline-hidden focus:ring-1 focus:ring-primary"
                    placeholder="<p>Ketik HTML di sini...</p>"
                ></textarea>
            </div>

            <!-- Visual Tiptap Editor Mode -->
            <div v-else class="prose prose-sm sm:prose-base dark:prose-invert max-w-none text-foreground leading-relaxed focus:outline-hidden">
                <EditorContent :editor="editor" />
            </div>
        </div>

        <!-- Link Modal Dialog -->
        <div 
            v-if="isLinkModalOpen" 
            class="fixed inset-0 z-50 bg-background/80 backdrop-blur-xs flex items-center justify-center p-4 animate-in fade-in duration-100"
            @click.self="closeLinkModal"
        >
            <div class="bg-card border shadow-xl rounded-2xl max-w-md w-full p-5 space-y-4 animate-in zoom-in-95 duration-150">
                <div class="flex items-center justify-between border-b pb-3">
                    <div class="flex items-center gap-2">
                        <Link2 class="h-4 w-4 text-primary" />
                        <h4 class="font-bold text-sm">Sisipkan Tautan / URL</h4>
                    </div>
                    <button type="button" @click="closeLinkModal" class="p-1 rounded-md text-muted-foreground hover:text-foreground">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-1.5">
                    <label class="text-xs font-semibold text-muted-foreground">Alamat URL Tautan</label>
                    <input
                        v-model="linkUrl"
                        type="url"
                        placeholder="https://contoh.com atau /kebijakan-privasi"
                        @keyup.enter="applyLink"
                        autofocus
                        class="w-full h-9 rounded-xl border border-input bg-transparent px-3 py-1 text-xs shadow-2xs focus:outline-hidden focus:ring-1 focus:ring-primary"
                    />
                    <p class="text-[11px] text-muted-foreground">
                        Tautan eksternal otomatis dibuka di tab baru dengan proteksi keamanan.
                    </p>
                </div>

                <div class="flex items-center justify-between pt-2 border-t">
                    <Button 
                        v-if="editor?.isActive('link')" 
                        type="button" 
                        variant="destructive" 
                        size="sm" 
                        class="text-xs" 
                        @click="removeLink"
                    >
                        Hapus Tautan
                    </Button>
                    <div v-else></div>

                    <div class="flex items-center gap-2">
                        <Button type="button" variant="ghost" size="sm" class="text-xs" @click="closeLinkModal">
                            Batal
                        </Button>
                        <Button type="button" size="sm" class="text-xs font-bold" @click="applyLink">
                            Terapkan
                        </Button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Scoped adjustments for Tiptap editor internal classes */
:deep(.ProseMirror) {
    min-height: 260px;
    outline: none;
}

:deep(.ProseMirror p) {
    margin-bottom: 0.75rem;
}

:deep(.ProseMirror p:last-child) {
    margin-bottom: 0;
}

:deep(.ProseMirror h1) {
    font-size: 1.75rem;
    font-weight: 800;
    margin-top: 1.25rem;
    margin-bottom: 0.65rem;
    line-height: 1.25;
}

:deep(.ProseMirror h2) {
    font-size: 1.35rem;
    font-weight: 700;
    margin-top: 1.15rem;
    margin-bottom: 0.55rem;
    line-height: 1.3;
}

:deep(.ProseMirror h3) {
    font-size: 1.15rem;
    font-weight: 700;
    margin-top: 0.95rem;
    margin-bottom: 0.45rem;
    line-height: 1.35;
}

:deep(.ProseMirror ul) {
    list-style-type: disc;
    padding-left: 1.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.75rem;
}

:deep(.ProseMirror ol) {
    list-style-type: decimal;
    padding-left: 1.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.75rem;
}

:deep(.ProseMirror li) {
    margin-bottom: 0.35rem;
}

:deep(.ProseMirror blockquote) {
    border-left: 3px solid var(--primary, currentColor);
    padding-left: 1rem;
    margin-left: 0;
    margin-right: 0;
    margin-top: 0.75rem;
    margin-bottom: 0.75rem;
    font-style: italic;
    opacity: 0.9;
}

:deep(.ProseMirror hr) {
    border: none;
    border-top: 1px solid var(--border);
    margin: 1.5rem 0;
}

:deep(.ProseMirror code) {
    background-color: var(--muted);
    border-radius: 0.25rem;
    padding: 0.15rem 0.35rem;
    font-size: 0.85em;
    font-family: monospace;
}

:deep(.ProseMirror table) {
    border-collapse: collapse;
    table-layout: fixed;
    width: 100%;
    margin: 1rem 0;
    overflow: hidden;
}

:deep(.ProseMirror td),
:deep(.ProseMirror th) {
    min-width: 1em;
    border: 1px solid var(--border);
    padding: 0.5rem 0.75rem;
    vertical-align: top;
    box-sizing: border-box;
}

:deep(.ProseMirror th) {
    font-weight: bold;
    text-align: left;
    background-color: var(--muted);
}

:deep(.ProseMirror p.is-editor-empty:first-child::before) {
    color: var(--muted-foreground);
    content: attr(data-placeholder);
    float: left;
    height: 0;
    pointer-events: none;
}
</style>
