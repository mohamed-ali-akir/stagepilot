{{-- Statistics in Vue 3 (resources/js/vue/StatsPage.vue), fed by GET /api/v1/stats. --}}
<x-layouts::app :title="'Statistiques'">
    <flux:heading size="xl" level="1">Statistiques</flux:heading>
    <flux:text class="mb-6 mt-1">Seules les vraies réponses comptent (pas les réponses automatiques ni les adresses invalides).</flux:text>

    <div id="stats-app"></div>

    @vite('resources/js/vue/main.js')
</x-layouts::app>
