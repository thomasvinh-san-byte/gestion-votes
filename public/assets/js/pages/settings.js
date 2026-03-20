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
          AgToast.show('Param\u00e8tre enregistr\u00e9', 'success');
        } else {
          // Revert on failure
          revertField(ctrl, prev);
          AgToast.show('Erreur de sauvegarde', 'error');
        }
      })
      .catch(function() {
        revertField(ctrl, prev);
        AgToast.show('Erreur de sauvegarde', 'error');
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
  function loadSettings() {
    api('/api/v1/admin_settings.php', { action: 'list' })
      .then(function(r) {
        if (!r.body || !r.body.ok || !r.body.data) return;
        var settings = r.body.data;
        Object.keys(settings).forEach(function(key) {
          var el = document.getElementById(key);
          if (!el) return;
          var val = settings[key];
          if (el.type === 'checkbox') {
            el.checked = !!val;
            _prevValues.set(key, !!val);
          } else {
            el.value = val;
            _prevValues.set(key, val);
          }
        });
      })
      .catch(function(e) {
        console.warn('Settings load failed (graceful degradation):', e);
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
        '<div class="policy-info">' +
          '<div class="policy-name">' + escapeHtml(p.name) + '</div>' +
          '<div class="policy-details">' +
            escapeHtml(p.description || '') +
            (p.mode ? ' | mode\u00a0: ' + escapeHtml(p.mode) : '') +
            ' | seuil\u00a0: ' + Math.round((p.threshold || 0) * 100) + '%' +
            (p.include_proxies ? ' | procurations' : '') +
            (p.count_remote ? ' | distants' : '') +
          '</div>' +
        '</div>' +
        '<div class="policy-actions">' +
          '<button class="btn btn-ghost btn-xs btn-edit-quorum" data-id="' + escapeHtml(p.id) + '">Modifier</button>' +
          '<button class="btn btn-ghost btn-xs btn-danger-text btn-delete-quorum" data-id="' + escapeHtml(p.id) + '" data-name="' + escapeHtml(p.name) + '">Supprimer</button>' +
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
        if (!name) { AgToast.show('Nom requis', 'error'); return false; }
        var thresholdVal = parseFloat(modal.querySelector('#qpThreshold').value);
        if (isNaN(thresholdVal) || thresholdVal < 0 || thresholdVal > 1) {
          AgToast.show('Le seuil doit \u00eatre compris entre 0 et 1', 'error'); return false;
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
              AgToast.show(isEdit ? 'Politique mise \u00e0 jour' : 'Politique cr\u00e9\u00e9e', 'success');
              loadQuorumPolicies();
            } else {
              AgToast.show('Erreur lors de l\'enregistrement', 'error');
            }
          })
          .catch(function(err) { AgToast.show(err.message, 'error'); });
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
      quorumList.addEventListener('click', function(e) {
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
          Shared.openModal({
            title: 'Supprimer la politique de quorum',
            body: '<div class="alert alert-danger mb-3"><strong>Action irr\u00e9versible</strong></div>' +
              '<p>Supprimer la politique \u00ab\u00a0<strong>' + escapeHtml(name) + '</strong>\u00a0\u00bb\u00a0?</p>',
            confirmText: 'Supprimer',
            confirmClass: 'btn btn-danger',
            onConfirm: function() {
              Shared.btnLoading(delBtn, true);
              api('/api/v1/admin_quorum_policies.php', { action: 'delete', id: delBtn.dataset.id })
                .then(function(r) {
                  if (r.body && r.body.ok) {
                    AgToast.show('Politique supprim\u00e9e', 'success');
                    loadQuorumPolicies();
                  } else {
                    AgToast.show('Erreur lors de la suppression', 'error');
                  }
                })
                .catch(function(err) { AgToast.show(err.message, 'error'); })
                .finally(function() { Shared.btnLoading(delBtn, false); });
            }
          });
        }
      });
    }
  }

  // ═══════════════════════════════════════════════════════
  // EMAIL TEMPLATES
  // ═══════════════════════════════════════════════════════
  var _currentTemplate = null;

  function initEmailTemplates() {
    var templateList = document.getElementById('emailTemplateList');
    var templateEditor = document.getElementById('templateEditor');
    var btnSave = document.getElementById('btnSaveTemplate');
    var btnCancel = document.getElementById('btnCancelTemplate');
    var btnReset = document.getElementById('btnResetEmailTemplates');

    if (templateList) {
      templateList.addEventListener('click', function(e) {
        var btn = e.target.closest('.btn-edit-template');
        if (btn) {
          _currentTemplate = btn.dataset.template;
          var item = btn.closest('.settings-template-item');
          var name = item ? item.querySelector('.settings-template-name').textContent : _currentTemplate;
          var titleEl = document.getElementById('templateEditorTitle');
          if (titleEl) titleEl.textContent = '\u00c9diter\u00a0: ' + name;

          // Load template content from API
          api('/api/v1/admin_settings.php', { action: 'get_template', key: _currentTemplate })
            .then(function(r) {
              if (r.body && r.body.ok && r.body.data) {
                var subjectEl = document.getElementById('templateSubject');
                var bodyEl = document.getElementById('templateBody');
                if (subjectEl) subjectEl.value = r.body.data.subject || '';
                if (bodyEl) bodyEl.value = r.body.data.body || '';
              }
            })
            .catch(function() { console.warn('Template load failed'); });

          if (templateEditor) templateEditor.hidden = false;
          templateEditor.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    }

    if (btnSave) {
      btnSave.addEventListener('click', function() {
        var subject = document.getElementById('templateSubject');
        var body = document.getElementById('templateBody');
        api('/api/v1/admin_settings.php', {
          action: 'save_template',
          key: _currentTemplate,
          subject: subject ? subject.value : '',
          body: body ? body.value : ''
        })
          .then(function(r) {
            if (r.body && r.body.ok) {
              AgToast.show('Template enregistr\u00e9', 'success');
              if (templateEditor) templateEditor.hidden = true;
            } else {
              AgToast.show('Erreur de sauvegarde', 'error');
            }
          })
          .catch(function() { AgToast.show('Erreur de sauvegarde', 'error'); });
      });
    }

    if (btnCancel) {
      btnCancel.addEventListener('click', function() {
        if (templateEditor) templateEditor.hidden = true;
        _currentTemplate = null;
      });
    }

    if (btnReset) {
      btnReset.addEventListener('click', function() {
        Shared.openModal({
          title: 'R\u00e9initialiser les templates',
          body: '<p>Cr\u00e9er tous les templates avec le contenu par d\u00e9faut ? Les templates existants seront \u00e9cras\u00e9s.</p>',
          confirmText: 'R\u00e9initialiser',
          confirmClass: 'btn btn-warning',
          onConfirm: function() {
            api('/api/v1/admin_settings.php', { action: 'reset_templates' })
              .then(function(r) {
                if (r.body && r.body.ok) {
                  AgToast.show('Templates r\u00e9initialis\u00e9s', 'success');
                } else {
                  AgToast.show('Erreur lors de la r\u00e9initialisation', 'error');
                }
              })
              .catch(function() { AgToast.show('Erreur lors de la r\u00e9initialisation', 'error'); });
          }
        });
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
      api('/api/v1/admin_settings.php', { action: 'test_smtp' })
        .then(function(r) {
          if (r.body && r.body.ok) {
            AgToast.show('Connexion SMTP r\u00e9ussie', 'success');
          } else {
            AgToast.show('Connexion SMTP \u00e9chou\u00e9e', 'error');
          }
        })
        .catch(function() { AgToast.show('Connexion SMTP \u00e9chou\u00e9e', 'error'); })
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

    var storedContrast = localStorage.getItem('ag_high_contrast');
    if (storedContrast === '1') {
      document.documentElement.setAttribute('data-high-contrast', '1');
      var hcToggle = document.getElementById('settHighContrast');
      if (hcToggle) hcToggle.checked = true;
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
              AgToast.show('Taille du texte enregistr\u00e9e', 'success');
            }
          })
          .catch(function() {});
      });
    });

    // High contrast toggle
    var highContrastToggle = document.getElementById('settHighContrast');
    if (highContrastToggle) {
      highContrastToggle.addEventListener('change', function() {
        if (highContrastToggle.checked) {
          document.documentElement.setAttribute('data-high-contrast', '1');
          localStorage.setItem('ag_high_contrast', '1');
        } else {
          document.documentElement.removeAttribute('data-high-contrast');
          localStorage.setItem('ag_high_contrast', '0');
        }
        api('/api/v1/admin_settings.php', { action: 'update', key: 'settHighContrast', value: highContrastToggle.checked })
          .then(function(r) {
            if (r.body && r.body.ok) {
              AgToast.show('Contraste enregistr\u00e9', 'success');
            }
          })
          .catch(function() {});
      });
    }
  }

  // ═══════════════════════════════════════════════════════
  // TEMPLATE PREVIEW + VARIABLE TAG INSERTION
  // ═══════════════════════════════════════════════════════
  function updateTemplatePreview() {
    var body = document.getElementById('templateBody');
    var preview = document.getElementById('templatePreviewRender');
    if (!body || !preview) return;
    var text = body.value || '';
    if (!text.trim()) {
      preview.innerHTML = '<span style="color: var(--color-text-muted); font-style: italic; font-family: var(--font-sans);">Commencez \u00e0 saisir le corps du message pour voir l\'aper\u00e7u ici.</span>';
      return;
    }
    // Replace variables with sample values for preview
    var samples = {
      '{{nom}}': 'Jean Dupont',
      '{{date}}': '15/04/2026',
      '{{heure}}': '14h00',
      '{{lieu}}': 'Salle du Conseil',
      '{{syndic}}': 'Syndic Exemple SARL',
      '{{lien_vote}}': 'https://vote.example.com/abc123'
    };
    Object.keys(samples).forEach(function(k) {
      text = text.split(k).join('<strong>' + samples[k] + '</strong>');
    });
    preview.innerHTML = text.replace(/\n/g, '<br>');
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
        AgToast.show('Envoi d\'un email de test...', 'info');
        api('/api/v1/admin_settings.php', {
          action: 'test_template',
          key: _currentTemplate,
          subject: (document.getElementById('templateSubject') || {}).value || '',
          body: (document.getElementById('templateBody') || {}).value || ''
        })
          .then(function(r) {
            if (r.body && r.body.ok) {
              AgToast.show('Email de test envoy\u00e9', 'success');
            } else {
              AgToast.show('Erreur d\'envoi', 'error');
            }
          })
          .catch(function() { AgToast.show('Erreur d\'envoi', 'error'); });
      });
    }
  }

  // ═══════════════════════════════════════════════════════
  // SECTION SAVE BUTTONS + UNSAVED-DOT TRACKING
  // ═══════════════════════════════════════════════════════
  function saveSection(card, section) {
    // Gather all inputs within the card that have IDs and trigger saves
    var inputs = card.querySelectorAll('input[id], select[id], textarea[id]');
    var savePromises = [];
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
          .catch(function() {})
      );
    });
    Promise.all(savePromises).then(function() {
      AgToast.show('Section enregistr\u00e9e', 'success');
      // Hide unsaved dot
      var dot = card.querySelector('.unsaved-dot');
      if (dot) dot.hidden = true;
    });
  }

  function initSectionSave() {
    // Section save buttons
    document.querySelectorAll('.btn-save-section').forEach(function(btn) {
      btn.addEventListener('click', function() {
        var card = btn.closest('.card');
        if (!card) return;
        saveSection(card, btn.dataset.section);
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
  loadSettings();
  loadQuorumPolicies();

})();
