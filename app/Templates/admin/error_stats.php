<?php
/**
 * Template /admin/error-stats — vue admin des emissions d'erreurs.
 *
 * Source: ERR-V24-03 / D-10 — Plan 02.3 (Phase 2 v2.4).
 *
 * Variables attendues (extract via HtmlView::render) :
 *  - string         $period          Periode active ('7d' | '30d' | '90d').
 *  - array<string>  $periods         Liste des periodes disponibles.
 *  - array          $rows            Lignes agregees [{code, count, label}, ...].
 *  - bool           $statsAvailable  Vrai si fetchStats() a reussi.
 *  - string         $cspNonce        Nonce CSP (auto-injecte par HtmlView).
 *
 * Texte 100 % francais (CLAUDE.md). Aucune mention de "copropriete" ou "syndic".
 */

declare(strict_types=1);

/** @var string $period */
/** @var array<int, string> $periods */
/** @var array<int, array{code: string, count: int, label: string}> $rows */
/** @var bool $statsAvailable */
/** @var string $cspNonce */

$periodLabel = match ($period) {
    '30d' => '30 derniers jours',
    '90d' => '90 derniers jours',
    default => '7 derniers jours',
};
?><!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AG-VOTE — Statistiques erreurs</title>
  <link rel="icon" type="image/svg+xml" href="/favicon.svg">
  <link rel="stylesheet" href="/assets/css/app.css">
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="/assets/js/theme-init.js"></script>
  <script nonce="<?= htmlspecialchars($cspNonce, ENT_QUOTES, 'UTF-8') ?>" src="https://unpkg.com/htmx.org@1.9.10" defer></script>
</head>
<body>
  <main class="page" role="main" aria-labelledby="error-stats-heading">
    <header class="page-header">
      <h1 id="error-stats-heading">Statistiques des erreurs</h1>
      <p class="page-subtitle">
        Suivi des codes d'erreur émis par la plateforme — <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>.
      </p>
    </header>

    <section aria-label="Filtre période" class="filters" id="period-filters">
      <span class="filters-label">Période :</span>
      <?php foreach ($periods as $p): ?>
        <a
          href="/admin/error-stats?period=<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>"
          hx-get="/admin/error-stats?period=<?= htmlspecialchars($p, ENT_QUOTES, 'UTF-8') ?>"
          hx-target="#error-stats-table"
          hx-select="#error-stats-table"
          hx-push-url="true"
          class="filter-pill<?= $p === $period ? ' is-active' : '' ?>"
          aria-current="<?= $p === $period ? 'page' : 'false' ?>">
          <?php
            echo match ($p) {
                '7d' => '7 jours',
                '30d' => '30 jours',
                '90d' => '90 jours',
                default => htmlspecialchars($p, ENT_QUOTES, 'UTF-8'),
            };
          ?>
        </a>
      <?php endforeach; ?>
    </section>

    <?php if (!$statsAvailable): ?>
      <div class="banner banner-warning" role="alert">
        <strong>Métriques indisponibles.</strong>
        La requête d'agrégation a échoué. Réessayez plus tard ou consultez les journaux applicatifs.
      </div>
    <?php else: ?>
      <div class="banner banner-info" role="note">
        <strong>Limitation :</strong>
        Les métriques affichées proviennent de la table <code>audit_events</code>.
        Les codes émis par <code>api_fail()</code> ne sont pas tracés à ce stade —
        instrumentation prévue v2.5+.
      </div>
    <?php endif; ?>

    <section id="error-stats-table" aria-label="Tableau des émissions">
      <table class="data-table">
        <caption class="visually-hidden">
          Codes d'erreur émis sur la période <?= htmlspecialchars($periodLabel, ENT_QUOTES, 'UTF-8') ?>
        </caption>
        <thead>
          <tr>
            <th scope="col">Code</th>
            <th scope="col">Libellé</th>
            <th scope="col" class="num">Émissions (<?= htmlspecialchars($period, ENT_QUOTES, 'UTF-8') ?>)</th>
            <th scope="col" class="num">Taux next-step</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($rows === []): ?>
            <tr>
              <td colspan="4" class="empty-cell">
                Aucune émission enregistrée sur cette période.
                <span class="muted">
                  (Tracking limité aux actions <code>auth_account_locked</code>,
                  <code>device_blocked</code>, <code>auth_rate_limited</code>,
                  <code>admin.confirm.failed</code> à ce jour — voir 02.3-AUDIT.md.)
                </span>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($rows as $row): ?>
              <tr>
                <td><code><?= htmlspecialchars((string) $row['code'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= htmlspecialchars((string) $row['label'], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="num"><?= (int) $row['count'] ?></td>
                <td class="num muted" title="Instrumentation tracking next-step prévue v2.5+">
                  N/A
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>

      <p class="footnote muted">
        Colonne « Taux next-step » : N/A — l'instrumentation des clics sur les
        suggestions « next-step » sera ajoutée en v2.5+.
      </p>
    </section>
  </main>
</body>
</html>
