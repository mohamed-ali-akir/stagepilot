// Entry point of the Vue 3 module: each page has an empty <div> that Vue fills.
// The rest of the application stays in Livewire.
import { createApp } from 'vue';
import KanbanBoard from './KanbanBoard.vue';
import StatsPage from './StatsPage.vue';

const pages = { 'kanban-app': KanbanBoard, 'stats-app': StatsPage };

for (const [id, component] of Object.entries(pages)) {
    const element = document.getElementById(id);
    if (element) {
        createApp(component, { ...element.dataset }).mount(element);
    }
}
