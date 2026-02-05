/**
 * AG-VOTE Web Components Library
 *
 * This file imports and registers all custom elements.
 * Import this file once in your main layout to use all components.
 *
 * Usage in HTML:
 *   <script type="module" src="/assets/js/components/index.js"></script>
 *
 * Available components:
 *   - <ag-kpi>       : KPI display cards
 *   - <ag-badge>     : Status badges
 *   - <ag-spinner>   : Loading indicators
 *   - <ag-toast>     : Toast notifications
 *   - <ag-quorum-bar>: Quorum progress bars
 *   - <ag-vote-button>: Vote action buttons
 *   - <ag-popover>   : Contextual help popovers
 */

// Import all components (each file registers its own custom element)
import './ag-kpi.js';
import './ag-badge.js';
import './ag-spinner.js';
import './ag-toast.js';
import './ag-quorum-bar.js';
import './ag-vote-button.js';
import './ag-popover.js';

// Export for programmatic use
export { default as AgKpi } from './ag-kpi.js';
export { default as AgBadge } from './ag-badge.js';
export { default as AgSpinner } from './ag-spinner.js';
export { default as AgToast } from './ag-toast.js';
export { default as AgQuorumBar } from './ag-quorum-bar.js';
export { default as AgVoteButton } from './ag-vote-button.js';
export { default as AgPopover } from './ag-popover.js';

// Log registration in development
if (window.location.hostname === 'localhost') {
  console.log('[AG-VOTE] Web Components registered:', [
    'ag-kpi', 'ag-badge', 'ag-spinner', 'ag-toast', 'ag-quorum-bar', 'ag-vote-button', 'ag-popover'
  ]);
}
