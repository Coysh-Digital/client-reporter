import Sortable from 'sortablejs';
import {
    Chart,
    BarController,
    BarElement,
    CategoryScale,
    Filler,
    LineController,
    LineElement,
    LinearScale,
    PointElement,
    Tooltip,
} from 'chart.js';

Chart.register(BarController, BarElement, CategoryScale, Filler, LineController, LineElement, LinearScale, PointElement, Tooltip);

// Exposed for the report builder's drag-and-drop (initialised inline via Alpine
// x-init on the block list, which calls $wire.reorder with the new order).
window.Sortable = Sortable;

const accentColour = () =>
    getComputedStyle(document.documentElement).getPropertyValue('--color-accent').trim() || '#33406b';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /*
    | Shared interaction helpers behind the Blade components in
    | resources/views/components. Screens use the components; they never
    | reimplement menus, dialogs or toasts.
    */

    // <x-dropdown>: a menu with Escape, click-outside, arrow-key cycling and
    // focus returned to the trigger on close.
    Alpine.data('crDropdown', () => ({
        open: false,
        toggle() {
            this.open ? this.close() : this.show();
        },
        show() {
            this.open = true;
            this.$nextTick(() => this.items()[0]?.focus());
        },
        close(returnFocus = true) {
            if (!this.open) return;
            this.open = false;
            if (returnFocus) this.$refs.trigger?.focus();
        },
        items() {
            return Array.from(this.$root.querySelectorAll('[role="menuitem"]:not(:disabled)'));
        },
        move(delta) {
            const items = this.items();
            if (!items.length) return;
            const index = items.indexOf(document.activeElement);
            const next = (index + delta + items.length) % items.length;
            items[next].focus();
        },
    }));

    // <x-dialog>: wraps a native <dialog>, which gives focus containment,
    // top-layer stacking and Escape for free. Focus goes back where it was.
    Alpine.data('crDialog', (name) => ({
        name,
        previous: null,
        init() {
            this.$refs.dialog?.addEventListener('cancel', (event) => {
                event.preventDefault();
                this.close();
            });
        },
        show() {
            const dialog = this.$refs.dialog;
            if (!dialog || dialog.open) return;
            this.previous = document.activeElement;
            dialog.showModal();
            this.$nextTick(() => dialog.querySelector('[autofocus], input, select, textarea, button')?.focus());
        },
        close() {
            const dialog = this.$refs.dialog;
            if (!dialog || !dialog.open) return;
            dialog.close();
            this.previous?.focus?.();
        },
        backdrop(event) {
            if (event.target === this.$refs.dialog) this.close();
        },
    }));

    // <x-confirm-dialog>: one styled confirmation for the whole app, driven by
    // a `confirm` window event carrying the copy and a `then` callback.
    Alpine.data('crConfirm', () => ({
        title: '',
        message: '',
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        danger: false,
        then: null,
        previous: null,
        init() {
            this.$refs.dialog?.addEventListener('cancel', (event) => {
                event.preventDefault();
                this.close();
            });
        },
        ask(detail) {
            this.title = detail.title ?? 'Are you sure?';
            this.message = detail.message ?? '';
            this.confirmLabel = detail.confirm ?? 'Confirm';
            this.cancelLabel = detail.cancel ?? 'Cancel';
            this.danger = Boolean(detail.danger);
            this.then = typeof detail.then === 'function' ? detail.then : null;
            this.previous = document.activeElement;
            this.$refs.dialog.showModal();
            this.$nextTick(() => this.$refs.confirm?.focus());
        },
        accept() {
            const callback = this.then;
            this.close();
            callback?.();
        },
        close() {
            if (this.$refs.dialog?.open) this.$refs.dialog.close();
            this.then = null;
            this.previous?.focus?.();
        },
        backdrop(event) {
            if (event.target === this.$refs.dialog) this.close();
        },
    }));

    // <x-flash>: transient toasts for in-place actions (Livewire dispatches a
    // `toast` browser event). Session flashes render server-side instead.
    Alpine.data('crToasts', () => ({
        toasts: [],
        counter: 0,
        push(detail) {
            const id = ++this.counter;
            this.toasts.push({ id, message: detail.message ?? '', type: detail.type ?? 'ok' });
            setTimeout(() => this.dismiss(id), detail.duration ?? 5000);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }));

    // Unsaved-changes guard for settings-style forms: warns before the tab is
    // closed or a wire:navigate link is followed while edits are pending. The
    // component clears it by dispatching a `saved` browser event.
    Alpine.data('crUnsavedGuard', () => ({
        dirty: false,
        init() {
            this.onUnload = (event) => {
                if (!this.dirty) return;
                event.preventDefault();
                event.returnValue = '';
            };
            this.onNavigate = (event) => {
                if (this.dirty && !window.confirm('You have unsaved changes. Leave this page anyway?')) {
                    event.preventDefault();
                }
            };
            window.addEventListener('beforeunload', this.onUnload);
            document.addEventListener('livewire:navigate', this.onNavigate);
        },
        destroy() {
            window.removeEventListener('beforeunload', this.onUnload);
            document.removeEventListener('livewire:navigate', this.onNavigate);
        },
        touch() {
            this.dirty = true;
        },
        clean() {
            this.dirty = false;
        },
    }));

    // Sidebar collapse, remembered per browser.
    Alpine.data('crShell', () => ({
        mobileNav: false,
        collapsed: false,
        init() {
            try {
                this.collapsed = localStorage.getItem('cr.sidebar') === 'collapsed';
            } catch (error) {
                this.collapsed = false;
            }
        },
        toggleCollapsed() {
            this.collapsed = !this.collapsed;
            try {
                localStorage.setItem('cr.sidebar', this.collapsed ? 'collapsed' : 'expanded');
            } catch (error) {
                // Storage may be unavailable (private mode); the choice just won't persist.
            }
        },
        openMobile() {
            this.mobileNav = true;
            this.$nextTick(() => this.$refs.mobileClose?.focus());
        },
        closeMobile() {
            if (!this.mobileNav) return;
            this.mobileNav = false;
            this.$refs.mobileOpen?.focus();
        },
    }));

    // A small Alpine wrapper around Chart.js for the daily trend on the site
    // page. The canvas lives inside a wire:ignore block so Livewire never
    // clobbers it; Alpine creates the chart on init and tears it down on
    // destroy (including across wire:navigate visits).
    Alpine.data('crLineChart', (config) => ({
        chart: null,
        init() {
            const accent = accentColour();

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
