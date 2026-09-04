import Sortable from 'sortablejs';

// Exposed for the report builder's drag-and-drop (initialised inline via Alpine
// x-init on the block list, which calls $wire.reorder with the new order).
window.Sortable = Sortable;
