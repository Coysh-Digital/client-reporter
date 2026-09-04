import Sortable from 'sortablejs';
import Chart from 'chart.js/auto';

// Exposed for the report builder's drag-and-drop (initialised inline via Alpine
// x-init on the block list, which calls $wire.reorder with the new order).
window.Sortable = Sortable;

// A small Alpine wrapper around Chart.js for the per-integration mini charts on
// the site page. The canvas lives inside a wire:ignore block so Livewire never
// clobbers it; Alpine creates the chart on init and tears it down on destroy
// (including across wire:navigate visits).
document.addEventListener('alpine:init', () => {
    window.Alpine.data('crBarChart', (config) => ({
        chart: null,
        init() {
            const accent = getComputedStyle(document.documentElement)
                .getPropertyValue('--color-accent').trim() || '#33406b';

            this.chart = new Chart(this.$refs.canvas, {
                type: 'bar',
                data: {
                    labels: config.labels,
                    datasets: [{
                        label: config.label,
                        data: config.data,
                        backgroundColor: accent,
                        borderRadius: 4,
                        maxBarThickness: 48,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0 } },
                        x: { grid: { display: false } },
                    },
                },
            });
        },
        destroy() {
            this.chart?.destroy();
        },
    }));
});
