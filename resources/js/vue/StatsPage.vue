<script setup>
// Statistics page: numbers from GET /api/v1/stats, charts drawn by hand in SVG (no chart library).
// Colours: the two first slots of a palette checked for contrast and colour blindness (see .viz below).
import { computed, onMounted, ref } from 'vue';
import { api } from './api.js';

const stats = ref(null);
const failure = ref('');
const tooltip = ref(null);

// --- weekly chart geometry (viewBox units) ---
const W = 640;
const H = 240;
const PAD = { top: 16, right: 8, bottom: 36, left: 32 };
const plotW = W - PAD.left - PAD.right;
const plotH = H - PAD.top - PAD.bottom;

const weeks = computed(() => stats.value?.by_week ?? []);
const yMax = computed(() => {
    const max = Math.max(1, ...weeks.value.map((w) => w.sent));
    const step = max <= 5 ? 1 : max <= 10 ? 2 : 5;

    return Math.ceil(max / step) * step;
});
const yTicks = computed(() => {
    const step = yMax.value <= 5 ? 1 : yMax.value <= 10 ? 2 : 5;

    return Array.from({ length: yMax.value / step + 1 }, (_, i) => i * step);
});
const y = (value) => PAD.top + plotH - (value / yMax.value) * plotH;

const columns = computed(() => {
    const band = plotW / Math.max(1, weeks.value.length);
    const barW = Math.min(20, band * 0.3);

    return weeks.value.map((week, i) => {
        const center = PAD.left + band * i + band / 2;

        return {
            week,
            label: new Date(week.week).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' }),
            center,
            // two touching columns separated by a 2px gap: sent (slot 1) then answered (slot 2)
            bars: [
                { key: 'sent', x: center - barW - 1, w: barW, value: week.sent },
                { key: 'answered', x: center + 1, w: barW, value: week.answered },
            ],
        };
    });
});

// A column with a 4px rounded top and a square base on the baseline.
function columnPath(x, w, value) {
    const top = y(value);
    const base = y(0);
    const r = Math.min(4, w / 2, base - top);
    if (base - top <= 0) {
        return '';
    }

    return `M${x},${base} V${top + r} Q${x},${top} ${x + r},${top} H${x + w - r} Q${x + w},${top} ${x + w},${top + r} V${base} Z`;
}

function showTooltip(event, text) {
    const box = event.currentTarget.closest('figure').getBoundingClientRect();
    const x = event.clientX - box.left;
    // near the right edge, the tooltip goes to the left of the cursor so that it is never cut
    tooltip.value = { text, left: x, top: event.clientY - box.top - 8, flip: x > box.width * 0.6 };
}

const percent = (value) => (value === null ? '–' : `${value.toLocaleString('fr-FR')} %`);

onMounted(async () => {
    try {
        stats.value = (await api.get('/stats')).data;
    } catch (e) {
        failure.value = e.message;
    }
});
</script>

<template>
    <div class="viz space-y-8">
        <p v-if="failure" class="rounded-lg bg-red-50 px-4 py-2 text-sm text-red-800 dark:bg-red-950 dark:text-red-200" role="alert">{{ failure }}</p>
        <p v-else-if="!stats" class="text-sm text-zinc-500 dark:text-zinc-400">Chargement…</p>

        <template v-else>
            <!-- the headline numbers: tiles, not charts -->
            <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div v-for="tile in [
                    { label: 'Candidatures envoyées', value: stats.sent },
                    { label: 'Vraies réponses', value: stats.answered },
                    { label: 'Taux de réponse', value: percent(stats.reply_rate) },
                    { label: 'Délai moyen de réponse', value: stats.average_reply_days === null ? '–' : `${stats.average_reply_days.toLocaleString('fr-FR')} j` },
                ]" :key="tile.label" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                    <dt class="text-sm text-zinc-500 dark:text-zinc-400">{{ tile.label }}</dt>
                    <dd class="mt-1 text-2xl font-semibold text-zinc-900 dark:text-white">{{ tile.value }}</dd>
                </div>
            </dl>

            <!-- per week: two series on one axis (same unit: number of applications) -->
            <figure class="relative rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" @mouseleave="tooltip = null">
                <figcaption class="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <span class="text-sm font-semibold text-zinc-900 dark:text-white">Envois et réponses, 8 dernières semaines</span>
                    <span class="flex gap-4 text-xs text-zinc-600 dark:text-zinc-300" aria-hidden="true">
                        <span class="flex items-center gap-1.5"><span class="swatch" style="background: var(--series-1)"></span>Envoyées</span>
                        <span class="flex items-center gap-1.5"><span class="swatch" style="background: var(--series-2)"></span>Avec une vraie réponse</span>
                    </span>
                </figcaption>
                <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" role="img" aria-label="Nombre de candidatures envoyées et de vraies réponses par semaine (détail dans le tableau ci-dessous)">
                    <g v-for="tick in yTicks" :key="tick">
                        <line :x1="PAD.left" :x2="W - PAD.right" :y1="y(tick)" :y2="y(tick)" class="grid" />
                        <text :x="PAD.left - 8" :y="y(tick) + 4" text-anchor="end" class="axis">{{ tick }}</text>
                    </g>
                    <g v-for="column in columns" :key="column.week.week">
                        <path
                            v-for="bar in column.bars"
                            :key="bar.key"
                            :d="columnPath(bar.x, bar.w, bar.value)"
                            :style="{ fill: bar.key === 'sent' ? 'var(--series-1)' : 'var(--series-2)' }"
                        />
                        <!-- hit area wider than the marks, for the tooltip -->
                        <rect
                            :x="column.center - 24" :y="PAD.top" width="48" :height="plotH" fill="transparent"
                            @mousemove="showTooltip($event, `Semaine du ${column.label} : ${column.week.sent} envoyée(s), ${column.week.answered} réponse(s)`)"
                        />
                        <text :x="column.center" :y="H - PAD.bottom + 18" text-anchor="middle" class="axis">{{ column.label }}</text>
                    </g>
                </svg>
                <div v-if="tooltip" class="pointer-events-none absolute z-10 whitespace-nowrap rounded-md bg-zinc-900 px-2 py-1 text-xs text-white shadow dark:bg-white dark:text-zinc-900" :style="{ left: `${tooltip.left}px`, top: `${tooltip.top}px`, transform: tooltip.flip ? 'translate(calc(-100% - 12px), -100%)' : 'translate(12px, -100%)' }">
                    {{ tooltip.text }}
                </div>
                <details class="mt-3 text-sm">
                    <summary class="cursor-pointer text-zinc-600 dark:text-zinc-300">Voir les chiffres</summary>
                    <table class="mt-2 w-full text-left text-zinc-700 dark:text-zinc-200">
                        <thead><tr><th class="py-1 font-medium">Semaine du</th><th class="font-medium">Envoyées</th><th class="font-medium">Réponses</th><th class="font-medium">Taux</th></tr></thead>
                        <tbody>
                            <tr v-for="column in columns" :key="column.week.week" class="border-t border-zinc-200 dark:border-zinc-700">
                                <td class="py-1">{{ column.label }}</td><td>{{ column.week.sent }}</td><td>{{ column.week.answered }}</td><td>{{ percent(column.week.reply_rate) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </details>
            </figure>

            <!-- per sector: one series (reply rate), value at the tip of each bar -->
            <figure class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <figcaption class="mb-3 text-sm font-semibold text-zinc-900 dark:text-white">Taux de réponse par secteur</figcaption>
                <p v-if="!stats.by_sector.length" class="text-sm text-zinc-500 dark:text-zinc-400">Aucune candidature envoyée pour l'instant.</p>
                <ul class="space-y-3">
                    <li v-for="sector in stats.by_sector" :key="sector.sector" :title="`${sector.answered} réponse(s) sur ${sector.sent} envoi(s)`">
                        <div class="mb-1 flex justify-between gap-3 text-xs text-zinc-600 dark:text-zinc-300">
                            <span>{{ sector.sector }}</span>
                            <span>{{ percent(sector.reply_rate) }} · {{ sector.answered }}/{{ sector.sent }}</span>
                        </div>
                        <div class="h-3 rounded-sm bg-zinc-100 dark:bg-zinc-800" role="img" :aria-label="`${sector.sector} : ${percent(sector.reply_rate)}`">
                            <div class="h-3 rounded-r" :style="{ width: `${sector.reply_rate ?? 0}%`, background: 'var(--series-1)' }"></div>
                        </div>
                    </li>
                </ul>
            </figure>
        </template>
    </div>
</template>

<style scoped>
/* Two categorical slots, checked with the dataviz validator (contrast, colour blindness) in light and dark mode. */
.viz {
    --series-1: #2a78d6;
    --series-2: #eb6834;
    --grid: #e4e4e1;
    --axis: #6b6a66;
}
:global(.dark .viz) {
    --series-1: #3987e5;
    --series-2: #d95926;
    --grid: #33332f;
    --axis: #a3a29b;
}
.grid {
    stroke: var(--grid);
    stroke-width: 1;
}
.axis {
    fill: var(--axis);
    font-size: 11px;
}
.swatch {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 2px;
}
</style>
