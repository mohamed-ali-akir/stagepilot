<script setup>
// Follow-up board: one column per stage after sending. Dragging a card to "Entretien", "Réponse positive",
// "Refus" or "Stage obtenu" saves the new status through the API (PATCH /api/v1/applications/{id}/status).
// The first two columns are filled by the application itself (sending, reading replies): no drop there.
import { computed, onMounted, ref } from 'vue';
import { api } from './api.js';

const COLUMNS = [
    { key: 'sent', title: 'Envoyées', hint: 'en attente de réponse', statuses: ['sent', 'followed_up'] },
    { key: 'reply', title: 'Réponse reçue', hint: 'à lire dans Gmail', statuses: ['reply_received'] },
    { key: 'interview', title: 'Entretien', statuses: ['interview'], target: 'interview' },
    { key: 'positive', title: 'Réponse positive', statuses: ['positive'], target: 'positive' },
    { key: 'negative', title: 'Refus', statuses: ['negative'], target: 'negative' },
    { key: 'secured', title: 'Stage obtenu', statuses: ['internship_secured'], target: 'internship_secured' },
];
const TARGETS = COLUMNS.filter((c) => c.target);

const applications = ref([]);
const loading = ref(true);
const message = ref('');
const error = ref(false);
const dragged = ref(null);
const hovered = ref(null);

const byColumn = computed(() => Object.fromEntries(
    COLUMNS.map((column) => [column.key, applications.value.filter((a) => column.statuses.includes(a.status.value))]),
));

async function load() {
    const statuses = COLUMNS.flatMap((c) => c.statuses).join(',');
    let page = 1;
    let lastPage = 1;
    const all = [];
    do {
        const json = await api.get(`/applications?status=${statuses}&per_page=100&page=${page}`);
        all.push(...json.data);
        lastPage = json.meta.last_page;
        page++;
    } while (page <= lastPage);
    applications.value = all;
}

async function move(application, target) {
    if (!target || application.status.value === target) {
        return;
    }
    if (target === 'internship_secured' && !window.confirm(`Stage obtenu chez ${application.company.name} ? Tous les envois s'arrêteront.`)) {
        return;
    }

    const previous = application.status;
    application.status = { ...previous, value: target, label: TARGETS.find((c) => c.target === target).title };
    try {
        const json = await api.patch(`/applications/${application.id}/status`, { status: target });
        application.status = json.data.status;
        notify(`${application.company.name} : ${json.data.status.label}`);
    } catch (e) {
        application.status = previous; // the move did not happen: the card goes back
        notify(e.message, true);
    }
}

function notify(text, isError = false) {
    message.value = text;
    error.value = isError;
}

function onDrop(column) {
    hovered.value = null;
    if (dragged.value && column.target) {
        move(dragged.value, column.target);
    }
    dragged.value = null;
}

function formatDate(iso) {
    return iso ? new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }) : '';
}

onMounted(async () => {
    try {
        await load();
    } catch (e) {
        notify(e.message, true);
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div>
        <p class="sr-only" aria-live="polite">{{ message }}</p>
        <div v-if="message" class="mb-4 rounded-lg px-4 py-2 text-sm" :class="error ? 'bg-red-50 text-red-800 dark:bg-red-950 dark:text-red-200' : 'bg-zinc-100 text-zinc-800 dark:bg-zinc-800 dark:text-zinc-100'" role="status">
            {{ message }}
        </div>

        <p v-if="loading" class="text-sm text-zinc-500 dark:text-zinc-400">Chargement…</p>

        <div v-else class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6">
            <section
                v-for="column in COLUMNS"
                :key="column.key"
                class="flex min-h-40 flex-col rounded-xl border p-3 transition-colors"
                :class="[
                    column.target ? 'border-dashed' : 'border-solid',
                    hovered === column.key ? 'border-zinc-500 bg-zinc-100 dark:bg-zinc-800' : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900',
                ]"
                :aria-label="`Colonne ${column.title}`"
                @dragover="column.target && dragged ? ($event.preventDefault(), (hovered = column.key)) : null"
                @dragleave="hovered = null"
                @drop.prevent="onDrop(column)"
            >
                <header class="mb-2 flex items-baseline justify-between gap-2">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-white">{{ column.title }}</h2>
                    <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ byColumn[column.key].length }}</span>
                </header>
                <p v-if="column.hint" class="mb-2 text-xs text-zinc-500 dark:text-zinc-400">{{ column.hint }}</p>

                <ul class="flex flex-col gap-2">
                    <li
                        v-for="application in byColumn[column.key]"
                        :key="application.id"
                        draggable="true"
                        class="cursor-grab rounded-lg border border-zinc-200 bg-white p-3 shadow-xs active:cursor-grabbing dark:border-zinc-700 dark:bg-zinc-800"
                        :class="{ 'opacity-50': dragged?.id === application.id }"
                        @dragstart="dragged = application"
                        @dragend="dragged = null; hovered = null"
                    >
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ application.company.name }}</p>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ application.company.city }}
                            <template v-if="application.sent_at"> · envoyée le {{ formatDate(application.sent_at) }}</template>
                            <template v-if="application.status.value === 'followed_up'"> · relancée</template>
                        </p>
                        <!-- same action without a mouse (keyboard, screen reader, phone) -->
                        <label class="mt-2 block">
                            <span class="sr-only">Déplacer {{ application.company.name }} vers</span>
                            <select
                                class="w-full rounded-md border border-zinc-200 bg-transparent px-2 py-1 text-xs text-zinc-700 dark:border-zinc-600 dark:text-zinc-200"
                                :aria-label="`Déplacer ${application.company.name} vers`"
                                :value="''"
                                @change="move(application, $event.target.value); $event.target.value = ''"
                            >
                                <option value="" disabled>Déplacer vers…</option>
                                <option v-for="target in TARGETS" :key="target.key" :value="target.target" :disabled="application.status.value === target.target">
                                    {{ target.title }}
                                </option>
                            </select>
                        </label>
                    </li>
                </ul>
                <p v-if="!byColumn[column.key].length" class="mt-auto text-xs text-zinc-400 dark:text-zinc-500">
                    {{ column.target ? 'Glisse une carte ici' : 'Aucune' }}
                </p>
            </section>
        </div>
    </div>
</template>
