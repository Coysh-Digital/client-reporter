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

    // A daily line chart (filled area) for the per-integration trend on the site
    // page — daily visitors, uptime, Lighthouse score, search clicks, revenue.
    // Same wire:ignore + init/destroy lifecycle as crBarChart.
    window.Alpine.data('crLineChart', (config) => ({
        chart: null,
        init() {
            const accent = getComputedStyle(document.documentElement)
                .getPropertyValue('--color-accent').trim() || '#33406b';

            this.chart = new Chart(this.$refs.canvas, {
                type: 'line',
                data: {
                    labels: config.labels,
                    datasets: [{
                        label: config.label,
                        data: config.data,
                        borderColor: accent,
                        backgroundColor: accent + '22',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.3,
                        pointRadius: 0,
                        pointHoverRadius: 3,
                    }],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { intersect: false, mode: 'index' },
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { precision: 0 } },
                        x: { grid: { display: false }, ticks: { maxTicksLimit: 6, maxRotation: 0 } },
                    },
                },
            });
        },
        destroy() {
            this.chart?.destroy();
        },
    }));
});
