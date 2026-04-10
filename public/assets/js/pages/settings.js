/* GO-LIVE-STATUS: ready — Settings page JS. auto-save, tab switching, quorum CRUD. */
/**
 * settings.js — Settings page logic for AG-VOTE.
 *
 * Handles: tab switching, auto-save on change with toast feedback,
 *          quorum policy CRUD, distribution key management,
 *          email template editing, accessibility controls (text size, high contrast).
 *
 * Must be loaded AFTER utils.js, shared.js and shell.js.
 */
(function() {
  'use strict';

  // ═══════════════════════════════════════════════════════
  // TAB SWITCHING
  // ═══════════════════════════════════════════════════════
  var _currentTab = 'regles';

  function initTabs() {
    var tabs = document.querySelectorAll('.settings-sidenav-item');
    var panels = document.querySelectorAll('.settings-panel');

    tabs.forEach(function(tab) {
      tab.addEventListener('click', function() {
        var target = tab.dataset.stab;
        switchTab(target);
        // Update URL hash for direct linking
        history.replaceState(null, '', '#' + target);
      });
    });

    // Auto-select tab based on URL hash
    var hash = location.hash.replace('#', '');
    if (hash && document.getElementById('stab-' + hash)) {
      switchTab(hash);
    }
  }

  function switchTab(tabId) {
    var tabs = document.querySelectorAll('.settings-sidenav-item');
    var panels = document.querySelectorAll('.settings-panel');

    tabs.forEach(function(t) {
      var isActive = t.dataset.stab === tabId;
      t.classList.toggle('active', isActive);
      t.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    panels.forEach(function(p) {
      var isActive = p.id === 'stab-' + tabId;
      p.hidden = !isActive;
    });

    _currentTab = tabId;
  }

  // ═══════════════════════════════════════════════════════
  // AUTO-SAVE
  // ═══════════════════════════════════════════════════════
  var _prevValues = new Map();
  var _debounceTimers = {};

  function initAutoSave() {
    // Collect all form controls for auto-save
    var controls = document.querySelectorAll(
      '#main-content input[type="checkbox"], ' +
      '#main-content input[type="radio"], ' +
      '#main-content select, ' +
      '#main-content input[type="number"], ' +
      '#main-content input[type="email"], ' +
      '#main-content input[type="text"]'
    );

    controls.forEach(function(ctrl) {
      if (!ctrl.id) return; // skip controls without ID
      // Skip template editor fields (saved via dedicated button)
      if (ctrl.closest('#templateEditor')) return;

      // Store initial value
      if (ctrl.type === 'checkbox') {
        _prevValues.set(ctrl.id, ctrl.checked);
      } else {
        _prevValues.set(ctrl.id, ctrl.value);
      }

      var eventType = (ctrl.type === 'checkbox' || ctrl.type === 'radio' || ctrl.tagName === 'SELECT')
        ? 'change'
        : 'input';

      ctrl.addEventListener(eventType, function() {
        if (ctrl.type === 'text' || ctrl.type === 'email' || ctrl.type === 'number' || ctrl.type === 'password') {
          // Debounce text/number inputs 500ms
          clearTimeout(_debounceTimers[ctrl.id]);
          _debounceTimers[ctrl.id] = setTimeout(function() {
            saveField(ctrl);
          }, 500);
        } else {
          // Immediate save for toggles, selects, radios
          saveField(ctrl);
        }
      });
    });

    // CNIL level cards: update selected class on change
    document.querySelectorAll('input[name="cnilLevel"]').forEach(function(radio) {
      radio.addEventListener('change', function() {
        document.querySelectorAll('.settings-level-card').forEach(function(card) {
          card.classList.toggle('selected', card.querySelector('input[name="cnilLevel"]') === radio);
        });
      });
    });
  }

  function saveField(ctrl) {
    var key = ctrl.id;
    var value = ctrl.type === 'checkbox' ? ctrl.checked : ctrl.value;
    var prev = _prevValues.get(key);

    if (value === prev) return; // no change

    api('/api/v1/admin_settings.php', { action: 'update', key: key, value: value })
      .then(function(r) {
        if (r.body && r.body.ok) {
          _prevValues.set(key, value);
          AgToast.show('success', 'Param\u00e8tre enregistr\u00e9');
        } else {
          // Revert on failure
          revertField(ctrl, prev);
          AgToast.show('error', 'Erreur de sauvegarde');
        }
      })
      .catch(function() {
        revertField(ctrl, prev);
        AgToast.show('error', 'Erreur de sauvegarde');
      });
  }

  function revertField(ctrl, prev) {
    if (ctrl.type === 'checkbox') {
      ctrl.checked = prev;
    } else {
      ctrl.value = prev;
    }
  }

  // ═══════════════════════════════════════════════════════
  // LOAD SETTINGS FROM API
  // ═══════════════════════════════════════════════════════

  // LOOSE-01 fix: extracted populate logic so the snapshot can be re-applied
  // defensively if the input is not yet present in DOM at first try, and so a
  // reliable readiness signal can be exposed for tests / future regressions.
  var _settingsLoadedSnapshot = null;

  function _applySettingsSnapshot(settings) {
    if (!settings || typeof settings !== 'object') return 0;
    var applied = 0;
    Object.keys(settings).forEach(function(key) {
      var el = document.getElementById(key);
      if (!el) return;
      var val = settings[key];
      if (val === null || val === undefined) val = '';
      if (el.type === 'checkbox') {
        el.checked = !!val;
        _prevValues.set(key, !!val);
      } else {
        el.value = val;
        _prevValues.set(key, val);
      }
      applied++;
    });
    if (window.DEBUG_SETTINGS) {
      console.debug('[settings] _applySettingsSnapshot applied', applied, 'of', Object.keys(settings).length);
    }
    return applied;
  }

  function loadSettings() {
    // LOOSE-01 fix: use GET with query string instead of POST with JSON body.
    // The previous call passed a body, which forced api() into POST mode and
    // could race with CSRF/session middleware in fresh contexts. The list
    // endpoint is idempotent and side-effect free — GET is the correct verb.
    api('/api/v1/admin_settings.php?action=list', null, 'GET')
      .then(function(r) {
        if (!r.body || !r.body.ok || !r.body.data) {
          if (window.DEBUG_SETTINGS) {
            console.debug('[settings] loadSettings: empty response', r);
          }
          return;
        }
        var settings = r.body.data;
        _settingsLoadedSnapshot = settings;

        // Apply now (DOM is ready: script tag is at end of <body>)
        var applied = _applySettingsSnapshot(settings);

        // LOOSE-01 fix: defensive re-apply on next tick. Guards against any
        // late-attached panel content or dynamic UI mutations that may have
        // raced the first apply.
        setTimeout(function() { _applySettingsSnapshot(settings); }, 0);

        // Expose readiness signal for Playwright regression tests and future
        // race-condition debugging.
        window.__settingsLoaded = true;
        if (window.DEBUG_SETTINGS) {
          console.debug('[settings] loadSettings done; applied=', applied);
        }
        // APP_URL localhost warning
        var appUrlEl = document.getElementById('app_url') || document.querySelector('[data-setting="app_url"]') || document.querySelector('input[name="app_url"]');
        if (appUrlEl) {
          var appUrlVal = settings['app_url'] || settings['APP_URL'] || appUrlEl.value || '';
          var appEnv = settings['app_env'] || settings['APP_ENV'] || '';
          var safeEnvs = ['demo', 'development', 'dev', 'local'];
          var isSafeEnv = safeEnvs.some(function(e) { return appEnv.toLowerCase().indexOf(e) !== -1; });
          if (!isSafeEnv && appUrlVal.toLowerCase().indexOf('localhost') !== -1) {
            var warn = document.getElementById('appUrlLocalhostWarning');
            if (!warn) {
              warn = document.createElement('div');
              warn.id = 'appUrlLocalhostWarning';
              warn.setAttribute('role', 'alert');
              warn.style.cssText = 'color:#856404;background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:6px 10px;margin-top:4px;font-size:.85rem;';
              warn.textContent = 'APP_URL contient localhost — les liens dans les emails ne fonctionneront pas en production.';
              appUrlEl.parentNode.insertBefore(warn, appUrlEl.nextSibling);
            }
          }
        }
      })
      .catch(function(e) {
        // Settings load failed — graceful degradation
      });
  }

  // ═══════════════════════════════════════════════════════
  // POLICIES — QUORUM
  // ═══════════════════════════════════════════════════════
  var _quorumPolicies = [];

  function loadQuorumPolicies() {
    api('/api/v1/admin_quorum_policies.php')
      .then(function(r) {
        if (r.body && r.body.ok && r.body.data && Array.isArray(r.body.data.items)) {
          _quorumPolicies = r.body.data.items;
          renderQuorumList(_quorumPolicies);
        }
      })
      .catch(function() {
        var c = document.getElementById('settingsQuorumList');
        if (c) c.innerHTML = '<div class="text-center p-4 text-muted">Erreur de chargement</div>';
      });
  }

  function renderQuorumList(items) {
    var el = document.getElementById('settingsQuorumList');
    if (!el) return;
    if (!items.length) {
      el.innerHTML = '<ag-empty-state icon="generic" title="Aucune politique de quorum" description="Cr\u00e9ez une politique pour d\u00e9finir le seuil de pr\u00e9sence requis." action-label="Cr\u00e9er une politique" action-href="#addQuorumPolicy"></ag-empty-state>';
      return;
    }
    el.innerHTML = items.map(function(p) {
      return '<div class="policy-card">' +
        '<div class="policy-icon quorum">' +
          '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v18"/><path d="M3 7h18"/><path d="M6 7l-3 9h6"/><path d="M18 7l3 9h-6"/><circle cx="6" cy="16" r="3"/><circle cx="18" cy="16" r="3"/></svg>' +
        '</div>' +
        '<div class="policy-info">' +
          '<div class="policy-header">' +
            '<div class="policy-name">' + escapeHtml(p.name) + '</div>' +
            '<span class="policy-status-badge active">Active</span>' +
          '</div>' +
          '<div class="policy-details">' +
            escapeHtml(p.description || '') +
            (p.mode ? ' | mode\u00a0: ' + escapeHtml(p.mode) : '') +
            ' | seuil\u00a0: ' + Math.round((p.threshold || 0) * 100) + '%' +
            (p.include_proxies ? ' | procurations' : '') +
            (p.count_remote ? ' | distants' : '') +
          '</div>' +
        '</div>' +
        '<div class="policy-actions">' +
          '<ag-tooltip text="Modifier"><button class="btn btn-ghost btn-icon btn-xs btn-edit-quorum" aria-label="Modifier la politique de quorum" data-id="' + escapeHtml(p.id) + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg></button></ag-tooltip>' +
          '<ag-tooltip text="Supprimer"><button class="btn btn-ghost btn-icon btn-xs btn-danger-text btn-delete-quorum" aria-label="Supprimer la politique de quorum" data-id="' + escapeHtml(p.id) + '" data-name="' + escapeHtml(p.name) + '"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg></button></ag-tooltip>' +
        '</div>' +
      '</div>';
    }).join('');
  }

  function openQuorumModal(policy) {
    var isEdit = !!policy;
    var p = policy || {};

    var modeOptions = ['single', 'evolving', 'double'].map(function(m) {
      var sel = m === (p.mode || 'single') ? ' selected' : '';
      var labels = { single: 'Simple', evolving: '\u00c9volutif', double: 'Double convocation' };
      return '<option value="' + m + '"' + sel + '>' + (labels[m] || m) + '</option>';
    }).join('');

    var denOptions = ['eligible_members', 'eligible_weight'].map(function(d) {
      var sel = d === (p.denominator || 'eligible_members') ? ' selected' : '';
      var labels = { eligible_members: 'Membres \u00e9ligibles', eligible_weight: 'Poids \u00e9ligible' };
      return '<option value="' + d + '"' + sel + '>' + (labels[d] || d) + '</option>';
    }).join('');

    var den2Options = ['eligible_members', 'eligible_weight'].map(function(d) {
      var sel = d === (p.denominator2 || 'eligible_members') ? ' selected' : '';
      var labels = { eligible_members: 'Membres \u00e9ligibles', eligible_weight: 'Poids \u00e9ligible' };
      return '<option value="' + d + '"' + sel + '>' + (labels[d] || d) + '</option>';
    }).join('');

    var showCall2 = (p.mode === 'double' || p.mode === 'evolving') ? '' : ' hidden';

    Shared.openModal({
      title: isEdit ? 'Modifier la politique de quorum' : 'Nouvelle politique de quorum',
      body:
        '<div class="form-group mb-3">' +
          '<label class="form-label">Nom</label>' +
          '<input class="form-input" type="text" id="qpName" value="' + escapeHtml(p.name || '') + '">' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Description</label>' +
          '<input class="form-input" type="text" id="qpDesc" value="' + escapeHtml(p.description || '') + '">' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Mode</label>' +
          '<select class="form-input" id="qpMode">' + modeOptions + '</select>' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">D\u00e9nominateur</label>' +
          '<select class="form-input" id="qpDen">' + denOptions + '</select>' +
        '</div>' +
        '<div class="form-group mb-3">' +
          '<label class="form-label">Seuil (0 \u00e0 1)</label>' +
          '<input class="form-input" type="number" id="qpThreshold" min="0" max="1" step="0.01" value="' + (p.threshold != null ? p.threshold : '0.5') + '">' +
        '</div>' +
        '<div id="qpCall2Section"' + showCall2 + '>' +
          '<hr style="border-color:var(--color-border);margin:0.75rem 0;">' +
          '<div class="text-sm font-semibold mb-2" style="color:var(--color-text-secondary)">2e convocation / 2e tour</div>' +
          '<div class="form-group mb-3">' +
            '<label class="form-label">Seuil de convocation 2 (0 \u00e0 1)</label>' +
            '<input class="form-input" type="number" id="qpThresholdCall2" min="0" max="1" step="0.01" value="' + (p.threshold_call2 != null ? p.threshold_call2 : '') + '" placeholder="Optionnel">' +
          '</div>' +
          '<div class="form-group mb-3">' +
            '<label class="form-label">D\u00e9nominateur 2e tour</label>' +
            '<select class="form-input" id="qpDen2">' + den2Options + '</select>' +
          '</div>' +
          '<div class="form-group mb-3">' +
            '<label class="form-label">Seuil 2e tour (0 \u00e0 1)</label>' +
            '<input class="form-input" type="number" id="qpThreshold2" min="0" max="1" step="0.01" value="' + (p.threshold2 != null ? p.threshold2 : '') + '" placeholder="Optionnel">' +
          '</div>' +
        '</div>' +
        '<div class="flex gap-4 mb-3">' +
          '<label class="flex items-center gap-2 text-sm"><input type="checkbox" id="qpProxies"' + (p.include_proxies ? ' checked' : '') + '> Inclure les procurations</label>' +
          '<label class="flex items-center gap-2 text-sm"><input type="checkbox" id="qpRemote"' + (p.count_remote ? ' checked' : '') + '> Compter les distants</label>' +
        '</div>',
      confirmText: isEdit ? 'Enregistrer' : 'Cr\u00e9er',
      onConfirm: function(modal) {
        var name = modal.querySelector('#qpName').value.trim();
        if (!name) { AgToast.show('error', 'Nom requis'); return false; }
        var thresholdVal = parseFloat(modal.querySelector('#qpThreshold').value);
        if (isNaN(thresholdVal) || thresholdVal < 0 || thresholdVal > 1) {
          AgToast.show('error', 'Le seuil doit \u00eatre compris entre 0 et 1'); return false;
        }
        var mode = modal.querySelector('#qpMode').value;
        var payload = {
          name: name,
          description: modal.querySelector('#qpDesc').value.trim(),
          mode: mode,
          denominator: modal.querySelector('#qpDen').value,
          threshold: thresholdVal,
          include_proxies: modal.querySelector('#qpProxies').checked ? 1 : 0,
          count_remote: modal.querySelector('#qpRemote').checked ? 1 : 0
        };
        if (mode === 'double' || mode === 'evolving') {
          var tc2 = modal.querySelector('#qpThresholdCall2').value;
          var t2 = modal.querySelector('#qpThreshold2').value;
          if (tc2 !== '') payload.threshold_call2 = parseFloat(tc2);
          payload.denominator2 = modal.querySelector('#qpDen2').value;
          if (t2 !== '') payload.threshold2 = parseFloat(t2);
        }
        if (isEdit) payload.id = p.id;
        api('/api/v1/admin_quorum_policies.php', payload)
          .then(function(r) {
            if (r.body && r.body.ok) {
              AgToast.show('success', isEdit ? 'Politique mise \u00e0 jour' : 'Politique cr\u00e9\u00e9e');
              loadQuorumPolicies();
            } else {
              AgToast.show('error', 'Erreur lors de l\'enregistrement');
            }
          })
          .catch(function(err) { AgToast.show('error', err.message); });
      }
    });

    // Toggle call-2 section visibility based on mode selection
    setTimeout(function() {
      var modeSelect = document.getElementById('qpMode');
      var call2Section = document.getElementById('qpCall2Section');
      if (modeSelect && call2Section) {
        modeSelect.addEventListener('change', function() {
          call2Section.hidden = (this.value === 'single');
        });
      }
    }, 60);
  }

  function initQuorumPolicies() {
    var btnAdd = document.getElementById('btnAddQuorumPolicy');
    if (btnAdd) {
      btnAdd.addEventListener('click', function() { openQuorumModal(null); });
    }

    var quorumList = document.getElementById('settingsQuorumList');
    if (quorumList) {
      quorumList.addEventListener('click', async function(e) {
        // Edit
        var btn = e.target.closest('.btn-edit-quorum');
        if (btn) {
          var policy = _quorumPolicies.find(function(p) { return p.id === btn.dataset.id; });
          if (policy) openQuorumModal(policy);
          return;
        }
        // Delete
        btn = e.target.closest('.btn-delete-quorum');
        if (btn) {
          var name = btn.dataset.name || 'cette politique';
          var delBtn = btn;
          var ok = await AgConfirm.ask({
            title: 'Supprimer la politique de quorum',
            message: 'Supprimer la politique \u00ab\u00a0' + name + '\u00a0\u00bb ? Cette action est irr\u00e9versible.',
            confirmLabel: 'Supprimer',
            variant: 'danger'
          });
          if (!ok) return;
          Shared.btnLoading(delBtn, true);
          try {
            var r = await api('/api/v1/admin_quorum_policies.php', { action: 'delete', id: delBtn.dataset.id });
            if (r.body && r.body.ok) {
              AgToast.show('success', 'Politique supprim\u00e9e');
              loadQuorumPolicies();
            } else {
              AgToast.show('error', 'Erreur lors de la suppression');
            }
          } catch (err) {
            AgToast.show(err.message, 'error');
          } finally {
            Shared.btnLoading(delBtn, false);
          }
        }
      });
    }
  }

  // ═══════════════════════════════════════════════════════
  // EMAIL TEMPLATES
  // ═══════════════════════════════════════════════════════
  var _currentTemplate = null;
  var _currentTemplateId = null;

  function loadTemplate(key) {
    _currentTemplate = key;
    api('/api/v1/email_templates?type=' + encodeURIComponent(key) + '&include_variables=1')
      .then(function(r) {
        if (r.body && r.body.ok && r.body.data) {
          var items = r.body.data.items || [];
          var tpl = items[0] || {};
          var subjectEl = document.getElementById('templateSubject');
          var bodyEl = document.getElementById('templateBody');
          if (subjectEl) subjectEl.value = tpl.subject || '';
          if (bodyEl) bodyEl.value = tpl.body_html || '';
          // Store template ID for save/update
          _currentTemplateId = tpl.id || null;
        }
      })
      .catch(function() { /* template load failed — silent */ });
  }

  function initEmailTemplates() {
    var templateSelect = document.getElementById('templateSelect');
    var btnSave = document.getElementById('btnSaveTemplate');
    var btnReset = document.getElementById('btnResetTemplates');

    // Load initial template on first visit
    if (templateSelect) {
      loadTemplate(templateSelect.value);
      templateSelect.addEventListener('change', function() {
        loadTemplate(templateSelect.value);
      });
    }

    if (btnSave) {
      btnSave.addEventListener('click', function() {
        var subject = document.getElementById('templateSubject');
        var body = document.getElementById('templateBody');
        api('/api/v1/email_templates', {
          id: _currentTemplateId,
          template_type: _currentTemplate,
          subject: subject ? subject.value : '',
          body_html: body ? body.value : ''
        }, 'PUT')
          .then(function(r) {
            if (r.body && r.body.ok) {
              AgToast.show('success', 'Template enregistr\u00e9');
            } else {
              AgToast.show('error', 'Erreur de sauvegarde');
            }
          })
          .catch(function() { AgToast.show('error', 'Erreur de sauvegarde'); });
      });
    }

    if (btnReset) {
      btnReset.addEventListener('click', async function() {
        var ok = await AgConfirm.ask({
          title: 'R\u00e9initialiser les templates',
          message: 'Cr\u00e9er tous les templates avec le contenu par d\u00e9faut ? Les templates existants seront \u00e9cras\u00e9s.',
          confirmLabel: 'R\u00e9initialiser',
          variant: 'warning'
        });
        if (!ok) return;
        try {
          var r = await api('/api/v1/email_templates', { action: 'create_defaults' }, 'POST');
          if (r.body && r.body.ok) {
            AgToast.show('success', 'Templates r\u00e9initialis\u00e9s');
          } else {
            AgToast.show('error', 'Erreur lors de la r\u00e9initialisation');
          }
        } catch (err) {
          AgToast.show('error', 'Erreur lors de la r\u00e9initialisation');
        }
      });
    }
  }

  // ═══════════════════════════════════════════════════════
  // SMTP TEST
  // ═══════════════════════════════════════════════════════
  function initSmtpTest() {
    var btnTest = document.getElementById('btnTestSmtp');
    if (!btnTest) return;
    btnTest.addEventListener('click', function() {
      Shared.btnLoading(btnTest, true);
      api('/api/v1/email_templates_preview', { action: 'test_smtp', dry_run: true })
        .then(function(r) {
          if (r.body && r.body.ok) {
            AgToast.show('success', 'Connexion SMTP r\u00e9ussie');
          } else {
            AgToast.show('error', 'Connexion SMTP \u00e9chou\u00e9e');
          }
        })
        .catch(function() { AgToast.show('error', 'Connexion SMTP \u00e9chou\u00e9e'); })
        .finally(function() { Shared.btnLoading(btnTest, false); });
    });
  }

  // ═══════════════════════════════════════════════════════
  // ACCESSIBILITY CONTROLS
  // ═══════════════════════════════════════════════════════
  function initAccessibilityControls() {
    // Restore from localStorage on load
    var storedSize = localStorage.getItem('ag_text_size');
    if (storedSize) {
      var sizeMap = { normal: '16px', large: '18px', xlarge: '20px' };
      if (sizeMap[storedSize]) {
        document.documentElement.style.fontSize = sizeMap[storedSize];
      }
      var radio = document.querySelector('input[name="settTextSize"][value="' + storedSize + '"]');
      if (radio) radio.checked = true;
    }

    // Text size A/A+/A++
    document.querySelectorAll('input[name="settTextSize"]').forEach(function(radio) {
      radio.addEventListener('change', function() {
        var sizeMap = { normal: '16px', large: '18px', xlarge: '20px' };
        var fontSize = sizeMap[radio.value] || '16px';
        document.documentElement.style.fontSize = fontSize;
        localStorage.setItem('ag_text_size', radio.value);
        // Also save as tenant default via API
        api('/api/v1/admin_settings.php', { action: 'update', key: 'textSize', value: radio.value })
          .then(function(r) {
            if (r.body && r.body.ok) {
              AgToast.show('success', 'Taille du texte enregistr\u00e9e');
            }
          })
          .catch(function() {});
      });
    });

  }

  // ═══════════════════════════════════════════════════════
  // TEMPLATE PREVIEW + VARIABLE TAG INSERTION
  // ═══════════════════════════════════════════════════════
  var _previewDebounce = null;
  function updateTemplatePreview() {
    var body = document.getElementById('templateBody');
    var subject = document.getElementById('templateSubject');
    var preview = document.getElementById('templatePreviewRender');
    if (!body || !preview) return;
    var text = body.value || '';
    if (!text.trim()) {
      preview.innerHTML = '<span style="color: var(--color-text-muted); font-style: italic; font-family: var(--font-sans);">Commencez \u00e0 saisir le corps du message pour voir l\'aper\u00e7u ici.</span>';
      return;
    }
    // Debounce server calls 400ms
    clearTimeout(_previewDebounce);
    _previewDebounce = setTimeout(function() {
      api('/api/v1/email_templates_preview', {
        body_html: text,
        subject: subject ? subject.value : ''
      })
        .then(function(r) {
          if (r.body && r.body.ok && r.body.data) {
            preview.innerHTML = r.body.data.preview_html || text;
          } else {
            // Fallback: show raw text
            preview.innerHTML = text.replace(/\n/g, '<br>');
          }
        })
        .catch(function() {
          preview.innerHTML = text.replace(/\n/g, '<br>');
        });
    }, 400);
  }

  function initTemplatePreview() {
    // Attach live preview to textarea and subject
    var bodyEl = document.getElementById('templateBody');
    var subjectEl = document.getElementById('templateSubject');
    if (bodyEl) bodyEl.addEventListener('input', updateTemplatePreview);
    if (subjectEl) subjectEl.addEventListener('input', updateTemplatePreview);

    // Variable tag click-to-insert handler
    document.addEventListener('click', function(e) {
      var tag = e.target.closest('.variable-tag');
      if (!tag) return;
      e.preventDefault();
      var varText = tag.dataset.var;
      var textarea = document.getElementById('templateBody');
      if (!textarea) return;
      var start = textarea.selectionStart;
      var end = textarea.selectionEnd;
      var text = textarea.value;
      textarea.value = text.substring(0, start) + varText + text.substring(end);
      textarea.selectionStart = textarea.selectionEnd = start + varText.length;
      textarea.focus();
      updateTemplatePreview();
    });

    // Test email button
    var btnTest = document.getElementById('btnTestEmail');
    if (btnTest) {
      btnTest.addEventListener('click', function() {
        AgToast.show('info', 'Envoi d\'un email de test...');
        api('/api/v1/email_templates_preview', {
          template_id: _currentTemplateId,
          type: _currentTemplate,
          subject: (document.getElementById('templateSubject') || {}).value || '',
          body_html: (document.getElementById('templateBody') || {}).value || ''
        })
          .then(function(r) {
            if (r.body && r.body.ok) {
              AgToast.show('success', 'Email de test envoy\u00e9');
            } else {
              AgToast.show('error', 'Erreur d\'envoi');
            }
          })
          .catch(function() { AgToast.show('error', 'Erreur d\'envoi'); });
      });
    }
  }

  // ═══════════════════════════════════════════════════════
  // SECTION SAVE BUTTONS + UNSAVED-DOT TRACKING
  // ═══════════════════════════════════════════════════════
  function saveSection(card, section, triggerBtn) {
    // Gather all inputs within the card that have IDs and trigger saves
    var inputs = card.querySelectorAll('input[id], select[id], textarea[id]');
    var savePromises = [];
    if (triggerBtn) Shared.btnLoading(triggerBtn, true);
    inputs.forEach(function(ctrl) {
      // Skip template editor fields — handled separately
      if (ctrl.closest('#templateEditor')) return;
      var key = ctrl.id;
      var value = ctrl.type === 'checkbox' ? ctrl.checked : ctrl.value;
      savePromises.push(
        api('/api/v1/admin_settings.php', { action: 'update', key: key, value: value })
          .then(function(r) {
            if (r.body && r.body.ok) {
              _prevValues.set(key, value);
            }
          })
          .catch(function(err) { AgToast.show('error', 'Erreur de sauvegarde : ' + (err.message || key)); })
      );
    });
    Promise.all(savePromises).then(function() {
      AgToast.show('success', 'Section enregistrée');
      // Hide unsaved dot
      var dot = card.querySelector('.unsaved-dot');
      if (dot) dot.hidden = true;
    }).finally(function() {
      if (triggerBtn) Shared.btnLoading(triggerBtn, false);
    });
  }

  function initSectionSave() {
    // Section save buttons
    document.querySelectorAll('.btn-save-section').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var card = btn.closest('.card');
        if (!card) return;
        saveSection(card, btn.dataset.section, btn);
      });
    });

    // Unsaved-dot tracking — show dot when any field in a card changes
    document.querySelectorAll('.card').forEach(function(card) {
      var dot = card.querySelector('.unsaved-dot');
      if (!dot) return;
      card.querySelectorAll('input, select, textarea').forEach(function(field) {
        // Skip template editor fields
        if (field.closest('#templateEditor')) return;
        field.addEventListener('change', function() { dot.hidden = false; });
        field.addEventListener('input', function() { dot.hidden = false; });
      });
    });
  }

  // ═══════════════════════════════════════════════════════
  // UNSAVED CHANGES WARNING (D-12)
  // ═══════════════════════════════════════════════════════
  var _settingsSnapshot = {};
  var _settingsDirty = false;

  function captureSettingsSnapshot() {
    _settingsSnapshot = {};
    document.querySelectorAll('.settings-form input, .settings-form select, .settings-form textarea').forEach(function(el) {
      var key = el.name || el.id;
      if (!key) return;
      _settingsSnapshot[key] = el.type === 'checkbox' ? el.checked : el.value;
    });
    _settingsDirty = false;
  }

  function isSettingsDirty() {
    if (_settingsDirty) return true;
    var dirty = false;
    document.querySelectorAll('.settings-form input, .settings-form select, .settings-form textarea').forEach(function(el) {
      var key = el.name || el.id;
      if (!key) return;
      var current = el.type === 'checkbox' ? el.checked : el.value;
      if (_settingsSnapshot[key] !== undefined && _settingsSnapshot[key] !== current) dirty = true;
    });
    return dirty;
  }

  function initUnsavedWarning() {
    // Track dirty state from template editor fields (not auto-saved)
    var templateEditor = document.getElementById('templateEditor');
    if (templateEditor) {
      templateEditor.addEventListener('input', function(e) {
        if (e.target.closest('#templateEditor')) _settingsDirty = true;
      });
      templateEditor.addEventListener('change', function(e) {
        if (e.target.closest('#templateEditor')) _settingsDirty = true;
      });
    }

    // beforeunload warning for template editor unsaved changes
    window.addEventListener('beforeunload', function(e) {
      if (_settingsDirty) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    // Shell navigation intercept (if available)
    if (window.Shell && Shell.beforeNavigate) {
      Shell.beforeNavigate(async function() {
        if (_settingsDirty) {
          return await AgConfirm.ask({
            title: 'Quitter sans enregistrer ?',
            message: 'Vos modifications seront perdues.',
            confirmLabel: 'Quitter la page',
            variant: 'warning'
          });
        }
        return true;
      });
    }

    // Reset dirty on successful template save
    var btnSave = document.getElementById('btnSaveTemplate');
    if (btnSave) {
      btnSave.addEventListener('click', function() {
        // dirty will be reset on next successful save from initEmailTemplates
      }, true);
    }

    // Capture initial snapshot after settings load
    setTimeout(captureSettingsSnapshot, 500);
  }

  // ═══════════════════════════════════════════════════════
  // INIT
  // ═══════════════════════════════════════════════════════
  initTabs();
  initAutoSave();
  initSectionSave();
  initTemplatePreview();
  initQuorumPolicies();
  initEmailTemplates();
  initSmtpTest();
  initAccessibilityControls();
  initUnsavedWarning();
  loadSettings();
  loadQuorumPolicies();

})();
