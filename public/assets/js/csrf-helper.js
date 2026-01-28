/**
 * csrf-helper.js - Support CSRF pour HTMX et fetch
 * 
 * USAGE:
 * 1. Inclure dans le <head> APRÈS le snippet PHP:
 *    <?php require_once __DIR__ . '/../app/Core/Security/CsrfMiddleware.php'; ?>
 *    <?= CsrfMiddleware::metaTag() ?>
 *    <script src="/assets/js/csrf-helper.js"></script>
 * 
 * 2. OU utiliser le snippet JS généré par PHP:
 *    <?= CsrfMiddleware::jsSnippet() ?>
 */

(function() {
  'use strict';

  // Récupère le token depuis le meta tag
  function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : null;
  }

  const csrfToken = getCsrfToken();
  const csrfHeader = 'X-CSRF-Token';
  const csrfName = 'csrf_token';

  if (!csrfToken) {
    console.warn('[CSRF] Token not found. Ensure CsrfMiddleware::metaTag() is included.');
  }

  // HTMX: ajoute automatiquement le header CSRF à toutes les requêtes
  document.body.addEventListener('htmx:configRequest', function(e) {
    if (csrfToken) {
      e.detail.headers[csrfHeader] = csrfToken;
    }
  });

  // Wrapper fetch sécurisé
  window.secureFetch = function(url, options = {}) {
    options.headers = options.headers || {};
    if (csrfToken) {
      options.headers[csrfHeader] = csrfToken;
    }
    options.credentials = options.credentials || 'same-origin';
    return fetch(url, options);
  };

  // Wrapper pour les appels API JSON
  window.secureApiPost = async function(url, data = {}) {
    const response = await window.secureFetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify(data),
    });
    return response.json();
  };

  window.secureApiGet = async function(url) {
    const response = await window.secureFetch(url, {
      method: 'GET',
    });
    return response.json();
  };

  // Expose les constantes pour usage manuel
  window.CSRF = {
    token: csrfToken,
    header: csrfHeader,
    name: csrfName,
    
    // Ajoute un champ hidden à un formulaire
    addField: function(form) {
      if (!csrfToken) return;
      
      let input = form.querySelector('input[name="' + csrfName + '"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = csrfName;
        form.appendChild(input);
      }
      input.value = csrfToken;
    },
    
    // Initialise tous les formulaires de la page
    initForms: function() {
      document.querySelectorAll('form[method="post"], form[method="POST"]').forEach(form => {
        window.CSRF.addField(form);
      });
    },
  };

  // Auto-init des formulaires au chargement
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', window.CSRF.initForms);
  } else {
    window.CSRF.initForms();
  }

  // Re-init après HTMX swap (nouveaux formulaires)
  document.body.addEventListener('htmx:afterSwap', function() {
    window.CSRF.initForms();
  });

  console.log('[CSRF] Helper loaded. Token:', csrfToken ? 'OK' : 'MISSING');
})();
