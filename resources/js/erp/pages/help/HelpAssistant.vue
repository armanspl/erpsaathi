<template>
    <div class="mx-auto flex h-[calc(100vh-7.5rem)] max-w-3xl flex-col space-y-4 sm:h-[calc(100vh-8.5rem)]">
        <div class="flex shrink-0 flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">Help / AI Assistant</h1>
                <Breadcrumb :items="['Dashboard', 'Help / AI Assistant']" class="mt-1" />
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Ask how to use the ERP. Answers are short, step-by-step walks through the sidebar menus.
                </p>
            </div>
            <button type="button" class="btn-outline !py-1.5 !text-xs" :disabled="!messages.length || sending" @click="clearChat">
                Clear chat
            </button>
        </div>

        <div
            ref="scrollEl"
            class="flex-1 space-y-3 overflow-y-auto rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900"
        >
            <div v-if="!messages.length" class="space-y-4 py-6 text-center">
                <p class="text-sm text-slate-500 dark:text-slate-400">Try a common question:</p>
                <div class="flex flex-wrap justify-center gap-2">
                    <button
                        v-for="chip in suggestions"
                        :key="chip"
                        type="button"
                        class="rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-left text-xs font-medium text-slate-700 transition hover:border-primary-300 hover:bg-primary-50 hover:text-primary-800 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:border-primary-500/40 dark:hover:bg-primary-500/10"
                        :disabled="sending"
                        @click="ask(chip)"
                    >
                        {{ chip }}
                    </button>
                </div>
            </div>

            <div
                v-for="(m, idx) in messages"
                :key="idx"
                class="flex"
                :class="m.role === 'user' ? 'justify-end' : 'justify-start'"
            >
                <div
                    class="max-w-[90%] whitespace-pre-wrap rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed"
                    :class="m.role === 'user'
                        ? 'bg-primary-600 text-white'
                        : 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-100'"
                >
                    {{ m.content }}
                </div>
            </div>

            <div v-if="sending" class="flex justify-start">
                <div class="rounded-2xl bg-slate-100 px-3.5 py-2.5 text-sm text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    Thinking...
                </div>
            </div>
        </div>

        <form class="flex shrink-0 gap-2" @submit.prevent="submit">
            <input
                v-model="draft"
                type="text"
                class="form-input flex-1"
                maxlength="2000"
                placeholder="e.g. How do I add a student?"
                :disabled="sending"
                autocomplete="off"
            />
            <button type="submit" class="btn-primary shrink-0" :disabled="sending || !draft.trim()">
                {{ sending ? '...' : 'Send' }}
            </button>
        </form>
    </div>
</template>

<script setup>
import { nextTick, ref } from 'vue';
import Breadcrumb from '../../components/common/Breadcrumb.vue';
import client from '../../api/client';

const suggestions = [
    'How do I add a student?',
    'How do I prepare UDISE+ FORM S03?',
    'Where are School Settings?',
    'How do I collect a fee payment?',
    'How do I mark student attendance?',
];

const messages = ref([]);
const draft = ref('');
const sending = ref(false);
const scrollEl = ref(null);

async function scrollToBottom() {
    await nextTick();
    if (scrollEl.value) {
        scrollEl.value.scrollTop = scrollEl.value.scrollHeight;
    }
}

function clearChat() {
    messages.value = [];
    draft.value = '';
}

function ask(text) {
    draft.value = text;
    submit();
}

async function submit() {
    const text = draft.value.trim();
    if (!text || sending.value) return;

    messages.value.push({ role: 'user', content: text });
    draft.value = '';
    await scrollToBottom();

    const history = messages.value
        .slice(0, -1)
        .slice(-12)
        .map(({ role, content }) => ({ role, content }));

    sending.value = true;
    try {
        const { data } = await client.post('/help/assistant', {
            message: text,
            history,
        });
        messages.value.push({
            role: 'assistant',
            content: data.reply || 'No reply received.',
        });
    } catch (e) {
        const msg = e.response?.data?.message || 'Could not reach the assistant.';
        messages.value.push({
            role: 'assistant',
            content: msg,
        });
    } finally {
        sending.value = false;
        await scrollToBottom();
    }
}
</script>
