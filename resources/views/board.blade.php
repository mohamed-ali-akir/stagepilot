{{-- Follow-up board in Vue 3 (resources/js/vue/KanbanBoard.vue), fed by the REST API. --}}
<x-layouts::app :title="'Suivi'">
    <flux:heading size="xl" level="1">Suivi des réponses</flux:heading>
    <flux:text class="mb-6 mt-1">Quand une entreprise te répond, glisse sa carte dans la bonne colonne (ou utilise « Déplacer vers… »).</flux:text>

    <div id="kanban-app"></div>

    @vite('resources/js/vue/main.js')
</x-layouts::app>
