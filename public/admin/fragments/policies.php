<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
AuthMiddleware::requireRole('admin');

header('Content-Type: text/html; charset=utf-8');

$tenantId = AuthMiddleware::getCurrentTenantId();

// Fetch quorum policies
$quorumPolicies = db_all(
    'SELECT * FROM quorum_policies WHERE tenant_id = :tid ORDER BY name',
    [':tid' => $tenantId]
);

// Fetch vote policies
$votePolicies = db_all(
    'SELECT * FROM vote_policies WHERE tenant_id = :tid ORDER BY name',
    [':tid' => $tenantId]
);

/** Helper: escape output */
function e(mixed $val): string {
    return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
}

/** Format threshold (0-1) as percentage */
function fmtPct(mixed $val): string {
    if ($val === null || $val === '') return '--';
    return htmlspecialchars(number_format((float)$val * 100, 1, ',', '') . ' %', ENT_QUOTES, 'UTF-8');
}

/** Mode label */
function modeLabel(string $mode): string {
    return match($mode) {
        'single'   => 'Simple',
        'evolving' => 'Evolutif',
        'double'   => 'Double',
        default    => htmlspecialchars($mode, ENT_QUOTES, 'UTF-8'),
    };
}

/** Mode badge class */
function modeBadge(string $mode): string {
    return match($mode) {
        'single'   => 'badge-primary',
        'evolving' => 'badge-warning',
        'double'   => 'badge-info',
        default    => 'badge-neutral',
    };
}

/** Base label */
function baseLabel(string $base): string {
    return match($base) {
        'expressed'      => 'Exprimes',
        'total_eligible' => 'Total eligibles',
        default          => htmlspecialchars($base, ENT_QUOTES, 'UTF-8'),
    };
}

/** Denominator label */
function denLabel(string $den): string {
    return match($den) {
        'eligible_members' => 'Membres eligibles',
        'eligible_weight'  => 'Poids eligibles',
        default            => htmlspecialchars($den, ENT_QUOTES, 'UTF-8'),
    };
}
?>

<!-- Page Header -->
<div class="admin-page-header">
  <div>
    <h2 class="admin-page-title">Politiques de vote et quorum</h2>
    <p class="admin-page-subtitle">Configurez les regles de quorum et de vote pour vos seances.</p>
  </div>
</div>

<!-- Tabs -->
<div class="tabs" id="policies-tabs">
  <button class="tab active" data-tab="quorum" onclick="AdminPolicies.switchTab('quorum')">
    Politiques de quorum
    <span class="badge badge-neutral ml-1"><?= count($quorumPolicies) ?></span>
  </button>
  <button class="tab" data-tab="vote" onclick="AdminPolicies.switchTab('vote')">
    Politiques de vote
    <span class="badge badge-neutral ml-1"><?= count($votePolicies) ?></span>
  </button>
</div>

<!-- ═══════════════ QUORUM POLICIES TAB ═══════════════ -->
<div class="tab-panel mt-4" id="panel-quorum">

  <div class="admin-table-toolbar">
    <div class="admin-table-search">
      <span class="text-sm text-secondary"><?= count($quorumPolicies) ?> politique(s) de quorum</span>
    </div>
    <div class="admin-table-actions">
      <button class="btn btn-primary btn-sm" onclick="AdminPolicies.openQuorumCreate()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle politique
      </button>
    </div>
  </div>

  <?php if (empty($quorumPolicies)): ?>
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
      </svg>
      <div class="empty-state-title">Aucune politique de quorum</div>
      <div class="empty-state-description">Creez votre premiere politique de quorum pour configurer les seuils de vos seances.</div>
      <button class="btn btn-primary" onclick="AdminPolicies.openQuorumCreate()">Creer une politique</button>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Mode</th>
              <th>Denominateur</th>
              <th>Seuil</th>
              <th>Proxies</th>
              <th>Date</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($quorumPolicies as $qp): ?>
            <tr>
              <td>
                <div class="font-medium"><?= e($qp['name']) ?></div>
                <?php if (!empty($qp['description'])): ?>
                  <div class="text-xs text-muted truncate" style="max-width:200px;"><?= e($qp['description']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge <?= modeBadge($qp['mode']) ?>"><?= modeLabel($qp['mode']) ?></span></td>
              <td class="text-sm"><?= denLabel($qp['denominator']) ?></td>
              <td>
                <span class="font-medium"><?= fmtPct($qp['threshold']) ?></span>
                <?php if ($qp['threshold_call2'] !== null): ?>
                  <br><span class="text-xs text-muted">2e conv.: <?= fmtPct($qp['threshold_call2']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($qp['include_proxies']): ?>
                  <span class="badge badge-success">Oui</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Non</span>
                <?php endif; ?>
                <?php if ($qp['count_remote']): ?>
                  <span class="badge badge-info" title="Distanciel compte">Dist.</span>
                <?php endif; ?>
              </td>
              <td class="text-sm text-muted"><?= e(date('d/m/Y', strtotime($qp['created_at']))) ?></td>
              <td class="text-right">
                <button class="btn btn-ghost btn-sm btn-icon" title="Modifier"
                        onclick="AdminPolicies.openQuorumEdit('<?= e($qp['id']) ?>')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button class="btn btn-ghost btn-sm btn-icon text-danger" title="Supprimer"
                        onclick="AdminPolicies.openQuorumDelete('<?= e($qp['id']) ?>', '<?= e($qp['name']) ?>')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ═══════════════ VOTE POLICIES TAB ═══════════════ -->
<div class="tab-panel mt-4 hidden" id="panel-vote">

  <div class="admin-table-toolbar">
    <div class="admin-table-search">
      <span class="text-sm text-secondary"><?= count($votePolicies) ?> politique(s) de vote</span>
    </div>
    <div class="admin-table-actions">
      <button class="btn btn-primary btn-sm" onclick="AdminPolicies.openVoteCreate()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
        Nouvelle politique
      </button>
    </div>
  </div>

  <?php if (empty($votePolicies)): ?>
    <div class="empty-state">
      <svg class="empty-state-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M9 12l2 2 4-4"/>
        <rect x="3" y="3" width="18" height="18" rx="2"/>
      </svg>
      <div class="empty-state-title">Aucune politique de vote</div>
      <div class="empty-state-description">Creez votre premiere politique de vote pour definir les regles de majorite.</div>
      <button class="btn btn-primary" onclick="AdminPolicies.openVoteCreate()">Creer une politique</button>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="table-wrapper">
        <table class="table">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Base</th>
              <th>Seuil</th>
              <th>Abstention = Contre</th>
              <th>Date</th>
              <th class="text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($votePolicies as $vp): ?>
            <tr>
              <td>
                <div class="font-medium"><?= e($vp['name']) ?></div>
                <?php if (!empty($vp['description'])): ?>
                  <div class="text-xs text-muted truncate" style="max-width:200px;"><?= e($vp['description']) ?></div>
                <?php endif; ?>
              </td>
              <td><span class="badge badge-primary"><?= baseLabel($vp['base']) ?></span></td>
              <td class="font-medium"><?= fmtPct($vp['threshold']) ?></td>
              <td>
                <?php if ($vp['abstention_as_against']): ?>
                  <span class="badge badge-warning">Oui</span>
                <?php else: ?>
                  <span class="badge badge-neutral">Non</span>
                <?php endif; ?>
              </td>
              <td class="text-sm text-muted"><?= e(date('d/m/Y', strtotime($vp['created_at']))) ?></td>
              <td class="text-right">
                <button class="btn btn-ghost btn-sm btn-icon" title="Modifier"
                        onclick="AdminPolicies.openVoteEdit('<?= e($vp['id']) ?>')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                  </svg>
                </button>
                <button class="btn btn-ghost btn-sm btn-icon text-danger" title="Supprimer"
                        onclick="AdminPolicies.openVoteDelete('<?= e($vp['id']) ?>', '<?= e($vp['name']) ?>')">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                    <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                </button>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<!-- ═══════════════ QUORUM POLICY MODAL ═══════════════ -->
<div class="modal-backdrop" id="modal-quorum">
  <div class="modal" style="max-width:600px;" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-quorum-title">Nouvelle politique de quorum</h3>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="AdminPolicies.closeModal('modal-quorum')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="form-quorum" onsubmit="return false;">
        <input type="hidden" id="qp-id" value="">

        <div class="form-group mb-4">
          <label class="form-label form-label-required" for="qp-name">Nom</label>
          <input type="text" id="qp-name" class="form-input" required minlength="2" maxlength="255" placeholder="Ex: Quorum standard AG">
        </div>

        <div class="form-group mb-4">
          <label class="form-label" for="qp-description">Description</label>
          <textarea id="qp-description" class="form-textarea" rows="2" placeholder="Description optionnelle..."></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="form-group">
            <label class="form-label form-label-required" for="qp-mode">Mode</label>
            <select id="qp-mode" class="form-select" onchange="AdminPolicies.onModeChange()">
              <option value="single">Simple</option>
              <option value="evolving">Evolutif</option>
              <option value="double">Double</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label form-label-required" for="qp-denominator">Denominateur</label>
            <select id="qp-denominator" class="form-select">
              <option value="eligible_members">Membres eligibles</option>
              <option value="eligible_weight">Poids eligibles</option>
            </select>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="form-group">
            <label class="form-label form-label-required" for="qp-threshold">Seuil (%)</label>
            <input type="number" id="qp-threshold" class="form-input" min="0" max="100" step="0.1" required placeholder="Ex: 50">
            <span class="form-helper">Pourcentage requis (0 - 100)</span>
          </div>
          <div class="form-group" id="qp-threshold-call2-group" style="display:none;">
            <label class="form-label" for="qp-threshold-call2">Seuil 2e convocation (%)</label>
            <input type="number" id="qp-threshold-call2" class="form-input" min="0" max="100" step="0.1" placeholder="Ex: 25">
            <span class="form-helper">Seuil reduit pour 2e convocation</span>
          </div>
        </div>

        <div class="flex gap-4 mb-4">
          <label class="flex items-center gap-2 text-sm" style="cursor:pointer;">
            <input type="checkbox" id="qp-include-proxies" checked>
            Inclure les procurations
          </label>
          <label class="flex items-center gap-2 text-sm" style="cursor:pointer;">
            <input type="checkbox" id="qp-count-remote" checked>
            Compter le distanciel
          </label>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="AdminPolicies.closeModal('modal-quorum')">Annuler</button>
      <button class="btn btn-primary" id="btn-quorum-save" onclick="AdminPolicies.saveQuorum()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ═══════════════ VOTE POLICY MODAL ═══════════════ -->
<div class="modal-backdrop" id="modal-vote">
  <div class="modal" style="max-width:600px;" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-vote-title">Nouvelle politique de vote</h3>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="AdminPolicies.closeModal('modal-vote')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="form-vote" onsubmit="return false;">
        <input type="hidden" id="vp-id" value="">

        <div class="form-group mb-4">
          <label class="form-label form-label-required" for="vp-name">Nom</label>
          <input type="text" id="vp-name" class="form-input" required minlength="2" maxlength="255" placeholder="Ex: Majorite simple">
        </div>

        <div class="form-group mb-4">
          <label class="form-label" for="vp-description">Description</label>
          <textarea id="vp-description" class="form-textarea" rows="2" placeholder="Description optionnelle..."></textarea>
        </div>

        <div class="grid grid-cols-2 gap-4 mb-4">
          <div class="form-group">
            <label class="form-label form-label-required" for="vp-base">Base de calcul</label>
            <select id="vp-base" class="form-select">
              <option value="expressed">Votes exprimes</option>
              <option value="total_eligible">Total des eligibles</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label form-label-required" for="vp-threshold">Seuil (%)</label>
            <input type="number" id="vp-threshold" class="form-input" min="0" max="100" step="0.1" required placeholder="Ex: 50">
            <span class="form-helper">Pourcentage requis (0 - 100)</span>
          </div>
        </div>

        <div class="mb-4">
          <label class="flex items-center gap-2 text-sm" style="cursor:pointer;">
            <input type="checkbox" id="vp-abstention-as-against">
            Compter les abstentions comme des votes contre
          </label>
          <span class="form-helper mt-1 block">Si coche, les abstentions sont decomptes comme des votes &laquo;contre&raquo;.</span>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="AdminPolicies.closeModal('modal-vote')">Annuler</button>
      <button class="btn btn-primary" id="btn-vote-save" onclick="AdminPolicies.saveVote()">Enregistrer</button>
    </div>
  </div>
</div>

<!-- ═══════════════ DELETE CONFIRMATION MODAL ═══════════════ -->
<div class="modal-backdrop" id="modal-delete">
  <div class="modal" onclick="event.stopPropagation()">
    <div class="modal-header">
      <h3 class="modal-title">Confirmer la suppression</h3>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="AdminPolicies.closeModal('modal-delete')">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="modal-body">
      <div class="alert alert-danger mb-4">
        <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
          <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>
        <div>
          <strong>Attention :</strong> cette action est irreversible.
          <div id="delete-message" class="mt-1"></div>
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="AdminPolicies.closeModal('modal-delete')">Annuler</button>
      <button class="btn btn-danger" id="btn-confirm-delete" onclick="AdminPolicies.confirmDelete()">Supprimer</button>
    </div>
  </div>
</div>

<!-- ═══════════════ QUORUM POLICIES DATA (for edit) ═══════════════ -->
<script id="quorum-policies-data" type="application/json"><?= json_encode(
    array_map(function($qp) {
        return [
            'id'              => $qp['id'],
            'name'            => $qp['name'],
            'description'     => $qp['description'] ?? '',
            'mode'            => $qp['mode'],
            'denominator'     => $qp['denominator'],
            'threshold'       => (float)$qp['threshold'],
            'threshold_call2' => $qp['threshold_call2'] !== null ? (float)$qp['threshold_call2'] : null,
            'denominator2'    => $qp['denominator2'] ?? null,
            'threshold2'      => $qp['threshold2'] !== null ? (float)$qp['threshold2'] : null,
            'include_proxies' => (bool)$qp['include_proxies'],
            'count_remote'    => (bool)$qp['count_remote'],
        ];
    }, $quorumPolicies),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
) ?></script>

<!-- ═══════════════ VOTE POLICIES DATA (for edit) ═══════════════ -->
<script id="vote-policies-data" type="application/json"><?= json_encode(
    array_map(function($vp) {
        return [
            'id'                    => $vp['id'],
            'name'                  => $vp['name'],
            'description'           => $vp['description'] ?? '',
            'base'                  => $vp['base'],
            'threshold'             => (float)$vp['threshold'],
            'abstention_as_against' => (bool)$vp['abstention_as_against'],
        ];
    }, $votePolicies),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG
) ?></script>

<script>
'use strict';

var AdminPolicies = (function() {

  var _deleteType = null;
  var _deleteId   = null;

  // ─── Data caches ───
  var _quorumData = JSON.parse(document.getElementById('quorum-policies-data').textContent || '[]');
  var _voteData   = JSON.parse(document.getElementById('vote-policies-data').textContent || '[]');

  // ─── Tab Switching ───
  function switchTab(tab) {
    document.querySelectorAll('#policies-tabs .tab').forEach(function(el) {
      el.classList.toggle('active', el.getAttribute('data-tab') === tab);
    });
    document.getElementById('panel-quorum').classList.toggle('hidden', tab !== 'quorum');
    document.getElementById('panel-vote').classList.toggle('hidden', tab !== 'vote');
  }

  // ─── Modal Helpers ───
  function openModal(id) {
    document.getElementById(id).classList.add('open');
  }

  function closeModal(id) {
    document.getElementById(id).classList.remove('open');
  }

  function _resetQuorumForm() {
    document.getElementById('qp-id').value = '';
    document.getElementById('qp-name').value = '';
    document.getElementById('qp-description').value = '';
    document.getElementById('qp-mode').value = 'single';
    document.getElementById('qp-denominator').value = 'eligible_members';
    document.getElementById('qp-threshold').value = '';
    document.getElementById('qp-threshold-call2').value = '';
    document.getElementById('qp-include-proxies').checked = true;
    document.getElementById('qp-count-remote').checked = true;
    onModeChange();
  }

  function _resetVoteForm() {
    document.getElementById('vp-id').value = '';
    document.getElementById('vp-name').value = '';
    document.getElementById('vp-description').value = '';
    document.getElementById('vp-base').value = 'expressed';
    document.getElementById('vp-threshold').value = '';
    document.getElementById('vp-abstention-as-against').checked = false;
  }

  // ─── Mode change handler (show/hide threshold_call2) ───
  function onModeChange() {
    var mode = document.getElementById('qp-mode').value;
    var group = document.getElementById('qp-threshold-call2-group');
    group.style.display = (mode === 'evolving' || mode === 'double') ? '' : 'none';
  }

  // ═══════════════ QUORUM CRUD ═══════════════

  function openQuorumCreate() {
    _resetQuorumForm();
    document.getElementById('modal-quorum-title').textContent = 'Nouvelle politique de quorum';
    document.getElementById('btn-quorum-save').textContent = 'Creer';
    openModal('modal-quorum');
  }

  function openQuorumEdit(id) {
    var policy = _quorumData.find(function(p) { return p.id === id; });
    if (!policy) {
      AdminApp.toast('Politique introuvable', 'danger');
      return;
    }
    _resetQuorumForm();
    document.getElementById('qp-id').value = policy.id;
    document.getElementById('qp-name').value = policy.name;
    document.getElementById('qp-description').value = policy.description || '';
    document.getElementById('qp-mode').value = policy.mode;
    document.getElementById('qp-denominator').value = policy.denominator;
    document.getElementById('qp-threshold').value = (policy.threshold * 100).toFixed(1);
    if (policy.threshold_call2 !== null) {
      document.getElementById('qp-threshold-call2').value = (policy.threshold_call2 * 100).toFixed(1);
    }
    document.getElementById('qp-include-proxies').checked = policy.include_proxies;
    document.getElementById('qp-count-remote').checked = policy.count_remote;
    onModeChange();

    document.getElementById('modal-quorum-title').textContent = 'Modifier la politique de quorum';
    document.getElementById('btn-quorum-save').textContent = 'Enregistrer';
    openModal('modal-quorum');
  }

  function openQuorumDelete(id, name) {
    _deleteType = 'quorum';
    _deleteId = id;
    document.getElementById('delete-message').textContent =
      'Voulez-vous vraiment supprimer la politique de quorum "' + name + '" ?';
    openModal('modal-delete');
  }

  function saveQuorum() {
    var id = document.getElementById('qp-id').value;
    var name = document.getElementById('qp-name').value.trim();
    var thresholdPct = parseFloat(document.getElementById('qp-threshold').value);

    if (!name || name.length < 2) {
      AdminApp.toast('Le nom doit contenir au moins 2 caracteres.', 'warning');
      return;
    }
    if (isNaN(thresholdPct) || thresholdPct < 0 || thresholdPct > 100) {
      AdminApp.toast('Le seuil doit etre entre 0 et 100.', 'warning');
      return;
    }

    var mode = document.getElementById('qp-mode').value;
    var thresholdCall2Raw = document.getElementById('qp-threshold-call2').value;
    var thresholdCall2 = null;
    if ((mode === 'evolving' || mode === 'double') && thresholdCall2Raw !== '') {
      thresholdCall2 = parseFloat(thresholdCall2Raw) / 100;
    }

    var payload = {
      type: 'quorum',
      action: id ? 'update' : 'create',
      name: name,
      description: document.getElementById('qp-description').value.trim(),
      mode: mode,
      denominator: document.getElementById('qp-denominator').value,
      threshold: thresholdPct / 100,
      threshold_call2: thresholdCall2,
      include_proxies: document.getElementById('qp-include-proxies').checked,
      count_remote: document.getElementById('qp-count-remote').checked
    };
    if (id) payload.id = id;

    document.getElementById('btn-quorum-save').disabled = true;

    AdminApp.apiFetch('/admin/api/policies.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    }).then(function(res) {
      document.getElementById('btn-quorum-save').disabled = false;
      if (res.data && res.data.ok) {
        AdminApp.toast(id ? 'Politique mise a jour.' : 'Politique creee.', 'success');
        closeModal('modal-quorum');
        _refresh();
      } else {
        AdminApp.toast(res.data.error || 'Erreur inconnue', 'danger');
      }
    }).catch(function() {
      document.getElementById('btn-quorum-save').disabled = false;
    });
  }

  // ═══════════════ VOTE CRUD ═══════════════

  function openVoteCreate() {
    _resetVoteForm();
    document.getElementById('modal-vote-title').textContent = 'Nouvelle politique de vote';
    document.getElementById('btn-vote-save').textContent = 'Creer';
    openModal('modal-vote');
  }

  function openVoteEdit(id) {
    var policy = _voteData.find(function(p) { return p.id === id; });
    if (!policy) {
      AdminApp.toast('Politique introuvable', 'danger');
      return;
    }
    _resetVoteForm();
    document.getElementById('vp-id').value = policy.id;
    document.getElementById('vp-name').value = policy.name;
    document.getElementById('vp-description').value = policy.description || '';
    document.getElementById('vp-base').value = policy.base;
    document.getElementById('vp-threshold').value = (policy.threshold * 100).toFixed(1);
    document.getElementById('vp-abstention-as-against').checked = policy.abstention_as_against;

    document.getElementById('modal-vote-title').textContent = 'Modifier la politique de vote';
    document.getElementById('btn-vote-save').textContent = 'Enregistrer';
    openModal('modal-vote');
  }

  function openVoteDelete(id, name) {
    _deleteType = 'vote';
    _deleteId = id;
    document.getElementById('delete-message').textContent =
      'Voulez-vous vraiment supprimer la politique de vote "' + name + '" ?';
    openModal('modal-delete');
  }

  function saveVote() {
    var id = document.getElementById('vp-id').value;
    var name = document.getElementById('vp-name').value.trim();
    var thresholdPct = parseFloat(document.getElementById('vp-threshold').value);

    if (!name || name.length < 2) {
      AdminApp.toast('Le nom doit contenir au moins 2 caracteres.', 'warning');
      return;
    }
    if (isNaN(thresholdPct) || thresholdPct < 0 || thresholdPct > 100) {
      AdminApp.toast('Le seuil doit etre entre 0 et 100.', 'warning');
      return;
    }

    var payload = {
      type: 'vote',
      action: id ? 'update' : 'create',
      name: name,
      description: document.getElementById('vp-description').value.trim(),
      base: document.getElementById('vp-base').value,
      threshold: thresholdPct / 100,
      abstention_as_against: document.getElementById('vp-abstention-as-against').checked
    };
    if (id) payload.id = id;

    document.getElementById('btn-vote-save').disabled = true;

    AdminApp.apiFetch('/admin/api/policies.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    }).then(function(res) {
      document.getElementById('btn-vote-save').disabled = false;
      if (res.data && res.data.ok) {
        AdminApp.toast(id ? 'Politique mise a jour.' : 'Politique creee.', 'success');
        closeModal('modal-vote');
        _refresh();
      } else {
        AdminApp.toast(res.data.error || 'Erreur inconnue', 'danger');
      }
    }).catch(function() {
      document.getElementById('btn-vote-save').disabled = false;
    });
  }

  // ═══════════════ DELETE ═══════════════

  function confirmDelete() {
    if (!_deleteType || !_deleteId) return;

    document.getElementById('btn-confirm-delete').disabled = true;

    AdminApp.apiFetch('/admin/api/policies.php', {
      method: 'POST',
      body: JSON.stringify({
        type: _deleteType,
        action: 'delete',
        id: _deleteId
      })
    }).then(function(res) {
      document.getElementById('btn-confirm-delete').disabled = false;
      if (res.data && res.data.ok) {
        AdminApp.toast('Politique supprimee.', 'success');
        closeModal('modal-delete');
        _refresh();
      } else {
        AdminApp.toast(res.data.error || 'Erreur lors de la suppression', 'danger');
      }
    }).catch(function() {
      document.getElementById('btn-confirm-delete').disabled = false;
    });
  }

  // ─── Refresh via HTMX ───
  function _refresh() {
    htmx.ajax('GET', '/admin/fragments/policies.php', {
      target: '#page-container',
      swap: 'innerHTML'
    });
  }

  // Public API
  return {
    switchTab:        switchTab,
    openModal:        openModal,
    closeModal:       closeModal,
    onModeChange:     onModeChange,
    openQuorumCreate: openQuorumCreate,
    openQuorumEdit:   openQuorumEdit,
    openQuorumDelete: openQuorumDelete,
    saveQuorum:       saveQuorum,
    openVoteCreate:   openVoteCreate,
    openVoteEdit:     openVoteEdit,
    openVoteDelete:   openVoteDelete,
    saveVote:         saveVote,
    confirmDelete:    confirmDelete
  };

})();
</script>
