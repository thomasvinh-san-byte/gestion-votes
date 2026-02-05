<?php

declare(strict_types=1);

namespace AgVote\Templates;

/**
 * Layout template system for AG-VOTE frontend pages.
 *
 * Provides reusable HTML structure to eliminate duplication across pages.
 * Pages can use these methods to render consistent headers, footers, and shells.
 *
 * @example
 * ```php
 * <?php
 * require_once __DIR__ . '/../app/Templates/Layout.php';
 * use AgVote\Templates\Layout;
 *
 * Layout::head('Séances', 'Gestion des séances');
 * Layout::shellStart('meetings');
 * // ... page content ...
 * Layout::shellEnd(['shared.js', 'meetings.js']);
 * ```
 */
class Layout
{
    /** @var string Current page name for sidebar highlighting */
    private static string $currentPage = '';

    /** @var array<string> Additional CSS files to include */
    private static array $cssFiles = [];

    /** @var array<string> Additional JS files to include */
    private static array $jsFiles = [];

    /** @var string Page role for data attribute */
    private static string $pageRole = 'viewer';

    /**
     * Render the HTML head section.
     *
     * @param string $title Page title (appended to "AG-VOTE")
     * @param string $description Meta description
     * @param array<string> $extraCss Additional CSS files to include
     * @param string $pageRole Role for data-page-role attribute
     */
    public static function head(
        string $title = '',
        string $description = 'AG-VOTE - Gestion des assemblées délibératives',
        array $extraCss = [],
        string $pageRole = 'viewer'
    ): void {
        self::$pageRole = $pageRole;
        $fullTitle = $title ? "{$title} — AG-VOTE" : 'AG-VOTE — Gestion des assemblées délibératives';
        ?>
<!doctype html>
<html lang="fr" data-page-role="<?= htmlspecialchars($pageRole) ?>">
<head>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="<?= htmlspecialchars($description) ?>">
  <meta name="csrf-token" content="">
  <title><?= htmlspecialchars($fullTitle) ?></title>
  <link rel="stylesheet" href="/assets/css/app.css">
<?php foreach ($extraCss as $css): ?>
  <link rel="stylesheet" href="<?= htmlspecialchars($css) ?>">
<?php endforeach; ?>
</head>
<body>
  <!-- Skip links for accessibility -->
  <a href="#main-content" class="skip-link">Aller au contenu principal</a>
  <a href="#main-nav" class="skip-link">Aller à la navigation</a>
<?php
    }

    /**
     * Start the application shell with sidebar.
     *
     * @param string $activePage The current page name for sidebar highlighting
     * @param bool $includeSidebar Whether to include the sidebar
     */
    public static function shellStart(string $activePage = '', bool $includeSidebar = true): void
    {
        self::$currentPage = $activePage;
        ?>
  <div class="app-shell">
<?php if ($includeSidebar): ?>
    <aside class="app-sidebar" data-include-sidebar data-page="<?= htmlspecialchars($activePage) ?>"></aside>
<?php endif; ?>
<?php
    }

    /**
     * Render the main content area opening tag.
     *
     * @param string $extraClasses Additional CSS classes
     * @param string $extraStyles Inline styles
     */
    public static function mainStart(string $extraClasses = '', string $extraStyles = ''): void
    {
        $classes = trim("app-main {$extraClasses}");
        $style = $extraStyles ? " style=\"{$extraStyles}\"" : '';
        echo "    <main class=\"{$classes}\" id=\"main-content\" role=\"main\"{$style}>\n";
    }

    /**
     * Render the main content area closing tag.
     */
    public static function mainEnd(): void
    {
        echo "    </main>\n";
    }

    /**
     * Render a page header with title and optional actions.
     *
     * @param string $title Page title
     * @param string $icon Icon name (from icons.svg)
     * @param string $actionsHtml HTML for action buttons
     */
    public static function pageHeader(string $title, string $icon = '', string $actionsHtml = ''): void
    {
        ?>
    <div class="page-header">
      <h1>
<?php if ($icon): ?>
        <svg class="icon icon-text icon-md" aria-hidden="true"><use href="/assets/icons.svg#icon-<?= htmlspecialchars($icon) ?>"></use></svg>
<?php endif; ?>
        <?= htmlspecialchars($title) ?>
      </h1>
<?php if ($actionsHtml): ?>
      <div class="page-header-actions">
        <?= $actionsHtml ?>
      </div>
<?php endif; ?>
    </div>
<?php
    }

    /**
     * Render the drawer component (used for mobile menu, info panels, etc.).
     */
    public static function drawer(): void
    {
        ?>
  <div class="drawer-backdrop" data-drawer-close aria-hidden="true"></div>
  <aside class="drawer" id="drawer" role="dialog" aria-modal="true" aria-labelledby="drawerTitle" aria-hidden="true">
    <header class="drawer-header">
      <button data-drawer-close class="btn btn-icon btn-ghost" aria-label="Fermer le panneau">✕</button>
      <h2 class="drawer-title" id="drawerTitle">—</h2>
    </header>
    <div class="drawer-body" id="drawerBody"></div>
  </aside>
<?php
    }

    /**
     * Close the application shell and render scripts.
     *
     * @param array<string> $scripts JS files to include (relative to /assets/js/)
     * @param string $inlineJs Inline JavaScript to include
     * @param bool $includeDrawer Whether to include the drawer component
     */
    public static function shellEnd(array $scripts = [], string $inlineJs = '', bool $includeDrawer = true): void
    {
        ?>
  </div>
<?php
        if ($includeDrawer) {
            self::drawer();
        }
        ?>

  <script type="module" src="/assets/js/components/index.js"></script>
  <script src="/assets/js/utils.js"></script>
  <script src="/assets/js/shared.js"></script>
  <script src="/assets/js/shell.js"></script>
<?php foreach ($scripts as $script): ?>
  <script src="/assets/js/<?= htmlspecialchars($script) ?>"></script>
<?php endforeach; ?>
<?php if ($inlineJs): ?>
  <script>
<?= $inlineJs ?>
  </script>
<?php endif; ?>
</body>
</html>
<?php
    }

    /**
     * Render a notification box container.
     *
     * @param string $id Element ID
     */
    public static function notificationBox(string $id = 'notif_box'): void
    {
        echo "      <div id=\"{$id}\" class=\"alert hidden mb-4\"></div>\n";
    }

    /**
     * Render a card/section container.
     *
     * @param string $title Card title
     * @param string $icon Icon name
     * @param string $id Element ID
     * @param string $extraClasses Additional CSS classes
     */
    public static function cardStart(string $title = '', string $icon = '', string $id = '', string $extraClasses = ''): void
    {
        $idAttr = $id ? " id=\"{$id}\"" : '';
        $classes = trim("settings-section {$extraClasses}");
        echo "        <div class=\"{$classes}\"{$idAttr}>\n";
        if ($title) {
            echo "          <h3>";
            if ($icon) {
                echo "<svg class=\"icon icon-text\" aria-hidden=\"true\"><use href=\"/assets/icons.svg#icon-{$icon}\"></use></svg> ";
            }
            echo htmlspecialchars($title) . "</h3>\n";
        }
    }

    /**
     * Close a card/section container.
     */
    public static function cardEnd(): void
    {
        echo "        </div>\n";
    }

    /**
     * Render an empty state placeholder.
     *
     * @param string $icon Icon name
     * @param string $title Title text
     * @param string $message Description text
     * @param string $actionHtml Optional action button HTML
     */
    public static function emptyState(string $icon, string $title, string $message, string $actionHtml = ''): void
    {
        ?>
      <div class="empty-state">
        <div class="empty-state-icon">
          <svg class="icon" style="width:3rem;height:3rem;" aria-hidden="true">
            <use href="/assets/icons.svg#icon-<?= htmlspecialchars($icon) ?>"></use>
          </svg>
        </div>
        <h3><?= htmlspecialchars($title) ?></h3>
        <p><?= htmlspecialchars($message) ?></p>
<?php if ($actionHtml): ?>
        <?= $actionHtml ?>
<?php endif; ?>
      </div>
<?php
    }

    /**
     * Render a stats bar with multiple stat items.
     *
     * @param array<array{id: string, label: string, class?: string}> $stats Stats configuration
     */
    public static function statsBar(array $stats): void
    {
        ?>
    <div class="stats-bar">
<?php foreach ($stats as $stat): ?>
      <div class="stat-item<?= isset($stat['class']) ? ' ' . $stat['class'] : '' ?>">
        <div class="stat-value" id="<?= htmlspecialchars($stat['id']) ?>">0</div>
        <div class="stat-label"><?= htmlspecialchars($stat['label']) ?></div>
      </div>
<?php endforeach; ?>
    </div>
<?php
    }

    /**
     * Render tab navigation.
     *
     * @param array<array{id: string, label: string, count?: bool}> $tabs Tabs configuration
     * @param string $activeTab Currently active tab ID
     */
    public static function tabsNav(array $tabs, string $activeTab = ''): void
    {
        ?>
    <nav class="tabs-nav" role="tablist" aria-label="Sections">
<?php foreach ($tabs as $i => $tab):
    $isActive = $activeTab ? ($tab['id'] === $activeTab) : ($i === 0);
    $activeClass = $isActive ? ' active' : '';
    $ariaSelected = $isActive ? 'true' : 'false';
?>
      <button class="tab-btn<?= $activeClass ?>" data-tab="<?= htmlspecialchars($tab['id']) ?>" role="tab" aria-selected="<?= $ariaSelected ?>" aria-controls="tab-<?= htmlspecialchars($tab['id']) ?>" id="tab-btn-<?= htmlspecialchars($tab['id']) ?>">
        <?= htmlspecialchars($tab['label']) ?>
<?php if (!empty($tab['count'])): ?>
        <span class="tab-count" id="tabCount<?= ucfirst($tab['id']) ?>" aria-label="<?= htmlspecialchars($tab['label']) ?>">0</span>
<?php endif; ?>
      </button>
<?php endforeach; ?>
    </nav>
<?php
    }

    /**
     * Start a tab content panel.
     *
     * @param string $id Tab ID
     * @param bool $active Whether this tab is initially active
     */
    public static function tabContentStart(string $id, bool $active = false): void
    {
        $activeClass = $active ? ' active' : '';
        echo "      <div class=\"tab-content{$activeClass}\" id=\"tab-{$id}\" role=\"tabpanel\" aria-labelledby=\"tab-btn-{$id}\" tabindex=\"0\">\n";
    }

    /**
     * End a tab content panel.
     */
    public static function tabContentEnd(): void
    {
        echo "      </div>\n";
    }

    /**
     * Render a toolbar with search and action buttons.
     *
     * @param string $searchId Search input ID
     * @param string $searchPlaceholder Search placeholder text
     * @param string $actionsHtml Action buttons HTML
     */
    public static function toolbar(string $searchId = '', string $searchPlaceholder = 'Rechercher...', string $actionsHtml = ''): void
    {
        ?>
        <div class="tab-toolbar">
<?php if ($actionsHtml): ?>
          <div class="flex gap-2">
            <?= $actionsHtml ?>
          </div>
<?php endif; ?>
<?php if ($searchId): ?>
          <input type="text" class="form-input tab-toolbar-search" id="<?= htmlspecialchars($searchId) ?>" placeholder="<?= htmlspecialchars($searchPlaceholder) ?>">
<?php endif; ?>
        </div>
<?php
    }

    /**
     * Render a grid container.
     *
     * @param string $id Element ID
     * @param string $class Grid class (e.g., 'meetings-grid', 'attendance-grid')
     */
    public static function gridStart(string $id, string $class = 'grid'): void
    {
        echo "        <div class=\"{$class}\" id=\"{$id}\">\n";
    }

    /**
     * Close a grid container.
     */
    public static function gridEnd(): void
    {
        echo "        </div>\n";
    }

    /**
     * Render a form group.
     *
     * @param string $label Label text
     * @param string $inputHtml Input element HTML
     * @param string $id Input ID for label association
     * @param string $helpText Optional help text
     */
    public static function formGroup(string $label, string $inputHtml, string $id = '', string $helpText = ''): void
    {
        ?>
              <div class="form-group mb-3">
                <label class="form-label"<?= $id ? " for=\"{$id}\"" : '' ?>><?= htmlspecialchars($label) ?></label>
                <?= $inputHtml ?>
<?php if ($helpText): ?>
                <span class="form-hint"><?= htmlspecialchars($helpText) ?></span>
<?php endif; ?>
              </div>
<?php
    }

    /**
     * Generate an SVG icon element.
     *
     * @param string $name Icon name
     * @param string $class Additional CSS classes
     * @return string HTML for the icon
     */
    public static function icon(string $name, string $class = ''): string
    {
        $classes = trim("icon {$class}");
        return "<svg class=\"{$classes}\" aria-hidden=\"true\"><use href=\"/assets/icons.svg#icon-{$name}\"></use></svg>";
    }

    /**
     * Generate a button element.
     *
     * @param string $text Button text
     * @param string $id Element ID
     * @param string $class Button classes
     * @param string $icon Optional icon name
     * @param array<string, string> $attrs Additional attributes
     * @return string HTML for the button
     */
    public static function button(
        string $text,
        string $id = '',
        string $class = 'btn btn-primary',
        string $icon = '',
        array $attrs = []
    ): string {
        $idAttr = $id ? " id=\"{$id}\"" : '';
        $attrsStr = '';
        foreach ($attrs as $key => $value) {
            $attrsStr .= " {$key}=\"" . htmlspecialchars($value) . "\"";
        }

        $iconHtml = $icon ? self::icon($icon, 'icon-text') . ' ' : '';

        return "<button class=\"{$class}\"{$idAttr}{$attrsStr}>{$iconHtml}" . htmlspecialchars($text) . "</button>";
    }
}
