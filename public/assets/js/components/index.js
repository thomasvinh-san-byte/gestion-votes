/**
 * AG-VOTE Web Components Library
 *
 * This file imports and registers all custom elements.
 * Import this file once in your main layout to use all components.
 *
 * Usage in HTML:
 *   <script type="module" src="/assets/js/components/index.js"></script>
 *
 * Available components (8 existing + 12 new):
 *   - <ag-kpi>              : KPI display cards
 *   - <ag-badge>            : Status badges / tags
 *   - <ag-spinner>          : Loading indicators
 *   - <ag-toast>            : Toast notifications
 *   - <ag-quorum-bar>       : Quorum progress bars
 *   - <ag-vote-button>      : Vote action buttons
 *   - <ag-popover>          : Contextual help popovers
 *   - <ag-searchable-select>: Searchable select dropdown
 *   - <ag-modal>            : Accessible modal dialog
 *   - <ag-pagination>       : Numbered pagination
 *   - <ag-breadcrumb>       : Breadcrumb trail
 *   - <ag-scroll-top>       : Scroll-to-top button
 *   - <ag-page-header>      : Page header with accent bar
 *   - <ag-donut>            : SVG donut chart
 *   - <ag-mini-bar>         : Inline mini bar chart
 *   - <ag-tooltip>          : CSS tooltip
 *   - <ag-time-input>       : HH:MM time input
 *   - <ag-tz-picker>        : Timezone selector
 *   - <ag-stepper>          : Horizontal stepper
 *   - <ag-confirm>          : Promise-based confirm dialog
 */

// Import all components (each file registers its own custom element)
// — Existing (restyled Phase 3)
import './ag-kpi.js';
import './ag-badge.js';
import './ag-spinner.js';
import './ag-toast.js';
import './ag-quorum-bar.js';
import './ag-vote-button.js';
import './ag-popover.js';
import './ag-searchable-select.js';
// — New (Phase 3)
import './ag-modal.js';
import './ag-pagination.js';
import './ag-breadcrumb.js';
import './ag-scroll-top.js';
import './ag-page-header.js';
import './ag-donut.js';
import './ag-mini-bar.js';
import './ag-tooltip.js';
import './ag-time-input.js';
import './ag-tz-picker.js';
import './ag-stepper.js';
import './ag-confirm.js';

// Export for programmatic use
export { default as AgKpi } from './ag-kpi.js';
export { default as AgBadge } from './ag-badge.js';
export { default as AgSpinner } from './ag-spinner.js';
export { default as AgToast } from './ag-toast.js';
export { default as AgQuorumBar } from './ag-quorum-bar.js';
export { default as AgVoteButton } from './ag-vote-button.js';
export { default as AgPopover } from './ag-popover.js';
export { default as AgSearchableSelect } from './ag-searchable-select.js';
export { default as AgModal } from './ag-modal.js';
export { default as AgPagination } from './ag-pagination.js';
export { default as AgBreadcrumb } from './ag-breadcrumb.js';
export { default as AgScrollTop } from './ag-scroll-top.js';
export { default as AgPageHeader } from './ag-page-header.js';
export { default as AgDonut } from './ag-donut.js';
export { default as AgMiniBar } from './ag-mini-bar.js';
export { default as AgTooltip } from './ag-tooltip.js';
export { default as AgTimeInput } from './ag-time-input.js';
export { default as AgTzPicker } from './ag-tz-picker.js';
export { default as AgStepper } from './ag-stepper.js';
export { default as AgConfirm } from './ag-confirm.js';

// Log registration in development
if (window.AG_DEBUG) {
  console.log('[AG-VOTE] Web Components registered:', [
    'ag-kpi', 'ag-badge', 'ag-spinner', 'ag-toast', 'ag-quorum-bar', 'ag-vote-button',
    'ag-popover', 'ag-searchable-select', 'ag-modal', 'ag-pagination', 'ag-breadcrumb',
    'ag-scroll-top', 'ag-page-header', 'ag-donut', 'ag-mini-bar', 'ag-tooltip',
    'ag-time-input', 'ag-tz-picker', 'ag-stepper', 'ag-confirm'
  ]);
}
