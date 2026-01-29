<?php
require_once __DIR__ . '/../../../app/bootstrap.php';
AuthMiddleware::requireRole('admin');

header('Content-Type: text/html; charset=utf-8');

$tenantId = AuthMiddleware::getCurrentTenantId();

$stmt = db()->prepare(
    'SELECT id, tenant_id, email, name, role, is_active, created_at, updated_at
     FROM users
     WHERE tenant_id = :tid
     ORDER BY created_at DESC'
);
$stmt->execute([':tid' => $tenantId]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allowedRoles = ['admin', 'operator', 'president', 'trust', 'viewer'];

$roleBadgeClass = [
    'admin'     => 'badge-danger',
    'operator'  => 'badge-primary',
    'president' => 'badge-warning',
    'trust'     => 'badge-info',
    'viewer'    => 'badge-neutral',
    'readonly'  => 'badge-neutral',
];

function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
?>

<!-- Page Header -->
<div class="admin-page-header">
  <div>
    <h2 class="admin-page-title">Utilisateurs</h2>
    <p class="admin-page-subtitle">Gestion des comptes utilisateurs et des cles API</p>
  </div>
  <button class="btn btn-primary" onclick="AdminUsers.openCreate()">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
      <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
    </svg>
    Nouvel utilisateur
  </button>
</div>

<!-- Users Table -->
<div class="card">
  <div class="table-wrapper">
    <table class="table">
      <thead>
        <tr>
          <th>Nom</th>
          <th>Email</th>
          <th>Role</th>
          <th>Statut</th>
          <th>Date creation</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($users)): ?>
        <tr>
          <td colspan="6" class="text-center text-muted p-6">Aucun utilisateur trouve.</td>
        </tr>
        <?php else: ?>
        <?php foreach ($users as $user): ?>
        <tr>
          <td class="font-medium"><?= e((string)($user['name'] ?? '')) ?></td>
          <td class="text-secondary"><?= e((string)($user['email'] ?? '--')) ?></td>
          <td>
            <span class="badge <?= e($roleBadgeClass[$user['role']] ?? 'badge-neutral') ?>">
              <?= e((string)$user['role']) ?>
            </span>
          </td>
          <td>
            <?php if ($user['is_active']): ?>
              <span class="badge badge-success">Actif</span>
            <?php else: ?>
              <span class="badge badge-danger">Inactif</span>
            <?php endif; ?>
          </td>
          <td class="text-secondary text-sm">
            <?= e(date('d/m/Y H:i', strtotime($user['created_at']))) ?>
          </td>
          <td>
            <div class="flex gap-1">
              <button class="btn btn-ghost btn-sm btn-icon" title="Modifier"
                      onclick="AdminUsers.openEdit('<?= e((string)$user['id']) ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                  <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                  <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                </svg>
              </button>
              <button class="btn btn-ghost btn-sm btn-icon" title="<?= $user['is_active'] ? 'Desactiver' : 'Activer' ?>"
                      onclick="AdminUsers.toggleActive('<?= e((string)$user['id']) ?>', <?= $user['is_active'] ? 'true' : 'false' ?>)">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                  <?php if ($user['is_active']): ?>
                    <path d="M18.36 6.64A9 9 0 1 1 5.64 6.64"/><line x1="12" y1="2" x2="12" y2="12"/>
                  <?php else: ?>
                    <path d="M5 12.55a11 11 0 0 1 14.08 0"/><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                  <?php endif; ?>
                </svg>
              </button>
              <button class="btn btn-ghost btn-sm btn-icon" title="Regenerer la cle API"
                      onclick="AdminUsers.regenerateKey('<?= e((string)$user['id']) ?>')">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
                  <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                  <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
              </button>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- API Key Result Display -->
<div id="api-key-result" class="card mt-4 hidden">
  <div class="card-body">
    <div class="alert alert-warning mb-4">
      <svg class="alert-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
      <div>
        <strong>Cle API generee</strong>
        <p class="mt-1">Cette cle ne sera affichee qu'une seule fois. Copiez-la maintenant.</p>
      </div>
    </div>
    <div class="api-key-display">
      <code id="api-key-value"></code>
      <button class="btn btn-ghost btn-sm btn-icon" title="Copier" onclick="AdminApp.copyToClipboard(document.getElementById('api-key-value').textContent)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16">
          <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
          <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
        </svg>
      </button>
    </div>
  </div>
</div>

<!-- User Modal (Create / Edit) -->
<div class="modal-backdrop" id="user-modal">
  <div class="modal">
    <div class="modal-header">
      <h3 class="modal-title" id="user-modal-title">Nouvel utilisateur</h3>
      <button class="btn btn-ghost btn-sm btn-icon" onclick="AdminUsers.closeModal()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18">
          <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
        </svg>
      </button>
    </div>
    <div class="modal-body">
      <form id="user-form" onsubmit="return false;">
        <input type="hidden" id="user-form-id" value="">
        <input type="hidden" id="user-form-action" value="create">

        <div class="form-group mb-4">
          <label class="form-label form-label-required" for="user-name">Nom</label>
          <input type="text" id="user-name" name="name" class="form-input"
                 placeholder="Nom complet" required minlength="2" maxlength="255">
        </div>

        <div class="form-group mb-4">
          <label class="form-label" for="user-email">Email</label>
          <input type="email" id="user-email" name="email" class="form-input"
                 placeholder="utilisateur@example.com">
          <span class="form-helper">Optionnel. Doit etre unique par tenant.</span>
        </div>

        <div class="form-group mb-4">
          <label class="form-label form-label-required" for="user-role">Role</label>
          <select id="user-role" name="role" class="form-select" required>
            <?php foreach ($allowedRoles as $role): ?>
            <option value="<?= e($role) ?>"><?= e(ucfirst($role)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="form-group mb-4">
          <label class="form-label flex items-center gap-2" for="user-active">
            <input type="checkbox" id="user-active" name="is_active" checked>
            Compte actif
          </label>
          <span class="form-helper">Un compte inactif ne peut pas s'authentifier.</span>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="AdminUsers.closeModal()">Annuler</button>
      <button class="btn btn-primary" id="user-form-submit" onclick="AdminUsers.submitForm()">
        Creer l'utilisateur
      </button>
    </div>
  </div>
</div>

<script>
var AdminUsers = (function() {
  'use strict';

  function openModal() {
    document.getElementById('user-modal').classList.add('open');
  }

  function closeModal() {
    document.getElementById('user-modal').classList.remove('open');
    document.getElementById('user-form').reset();
    document.getElementById('user-form-id').value = '';
    document.getElementById('user-form-action').value = 'create';
    document.getElementById('user-active').checked = true;
  }

  function openCreate() {
    closeModal();
    document.getElementById('user-modal-title').textContent = 'Nouvel utilisateur';
    document.getElementById('user-form-submit').textContent = "Creer l'utilisateur";
    document.getElementById('user-form-action').value = 'create';
    openModal();
  }

  function openEdit(id) {
    AdminApp.apiFetch('/admin/api/users.php?action=list')
      .then(function(res) {
        if (!res.data.ok) {
          AdminApp.toast('Impossible de charger les utilisateurs', 'danger');
          return;
        }
        var users = res.data.data.users || [];
        var user = null;
        for (var i = 0; i < users.length; i++) {
          if (users[i].id === id) {
            user = users[i];
            break;
          }
        }
        if (!user) {
          AdminApp.toast('Utilisateur introuvable', 'danger');
          return;
        }

        document.getElementById('user-form-id').value = user.id;
        document.getElementById('user-form-action').value = 'update';
        document.getElementById('user-modal-title').textContent = 'Modifier l\'utilisateur';
        document.getElementById('user-form-submit').textContent = 'Enregistrer';
        document.getElementById('user-name').value = user.name || '';
        document.getElementById('user-email').value = user.email || '';
        document.getElementById('user-role').value = user.role || 'operator';
        document.getElementById('user-active').checked = !!user.is_active;
        openModal();
      })
      .catch(function() {
        AdminApp.toast('Erreur lors du chargement', 'danger');
      });
  }

  function submitForm() {
    var action = document.getElementById('user-form-action').value;
    var payload = {
      action: action,
      name: document.getElementById('user-name').value.trim(),
      email: document.getElementById('user-email').value.trim(),
      role: document.getElementById('user-role').value,
      is_active: document.getElementById('user-active').checked
    };

    if (payload.name.length < 2) {
      AdminApp.toast('Le nom doit contenir au moins 2 caracteres', 'warning');
      return;
    }

    if (action === 'update') {
      payload.id = document.getElementById('user-form-id').value;
    }

    AdminApp.apiFetch('/admin/api/users.php', {
      method: 'POST',
      body: JSON.stringify(payload)
    }).then(function(res) {
      if (res.data.ok) {
        closeModal();

        if (action === 'create' && res.data.data && res.data.data.api_key) {
          showApiKey(res.data.data.api_key);
          AdminApp.toast('Utilisateur cree avec succes', 'success');
        } else {
          AdminApp.toast('Utilisateur mis a jour', 'success');
        }

        refreshPage();
      } else {
        AdminApp.toast(res.data.error || 'Erreur lors de la sauvegarde', 'danger');
      }
    }).catch(function() {
      AdminApp.toast('Erreur reseau', 'danger');
    });
  }

  function toggleActive(id, currentState) {
    var label = currentState ? 'desactiver' : 'activer';
    if (!window.confirm('Voulez-vous vraiment ' + label + ' cet utilisateur ?')) {
      return;
    }

    AdminApp.apiFetch('/admin/api/users.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'toggle_active', id: id })
    }).then(function(res) {
      if (res.data.ok) {
        AdminApp.toast('Statut utilisateur modifie', 'success');
        refreshPage();
      } else {
        AdminApp.toast(res.data.error || 'Erreur', 'danger');
      }
    }).catch(function() {
      AdminApp.toast('Erreur reseau', 'danger');
    });
  }

  function regenerateKey(id) {
    if (!window.confirm('Regenerer la cle API ? L\'ancienne cle sera invalidee.')) {
      return;
    }

    AdminApp.apiFetch('/admin/api/users.php', {
      method: 'POST',
      body: JSON.stringify({ action: 'regenerate_key', id: id })
    }).then(function(res) {
      if (res.data.ok && res.data.data && res.data.data.api_key) {
        showApiKey(res.data.data.api_key);
        AdminApp.toast('Cle API regeneree', 'success');
      } else {
        AdminApp.toast(res.data.error || 'Erreur lors de la regeneration', 'danger');
      }
    }).catch(function() {
      AdminApp.toast('Erreur reseau', 'danger');
    });
  }

  function showApiKey(key) {
    var container = document.getElementById('api-key-result');
    var valueEl = document.getElementById('api-key-value');
    valueEl.textContent = key;
    container.classList.remove('hidden');
    container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function refreshPage() {
    htmx.ajax('GET', '/admin/fragments/users.php', {
      target: '#page-container',
      swap: 'innerHTML'
    });
  }

  return {
    openCreate: openCreate,
    openEdit: openEdit,
    closeModal: closeModal,
    submitForm: submitForm,
    toggleActive: toggleActive,
    regenerateKey: regenerateKey
  };
})();
</script>
