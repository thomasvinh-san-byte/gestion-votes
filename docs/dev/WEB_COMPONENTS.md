# Web Components — Guide pour AG-VOTE

## Qu'est-ce qu'un Web Component ?

Les **Web Components** sont une technologie native du navigateur (pas un framework) qui permet de créer des éléments HTML personnalisés et réutilisables.

### Exemple concret

Au lieu d'écrire ceci partout :

```html
<div class="kpi-card">
  <div class="kpi-value">42</div>
  <div class="kpi-label">Présents</div>
</div>
```

Vous écrivez simplement :

```html
<ag-kpi value="42" label="Présents"></ag-kpi>
```

---

## Les 3 piliers des Web Components

### 1. Custom Elements

Définir vos propres balises HTML :

```javascript
class AgKpi extends HTMLElement {
  connectedCallback() {
    const value = this.getAttribute('value') || '0';
    const label = this.getAttribute('label') || '';

    this.innerHTML = `
      <div class="kpi-card">
        <div class="kpi-value">${value}</div>
        <div class="kpi-label">${label}</div>
      </div>
    `;
  }
}

// Enregistrer le composant
customElements.define('ag-kpi', AgKpi);
```

### 2. Shadow DOM

Encapsuler les styles pour éviter les conflits :

```javascript
class AgButton extends HTMLElement {
  constructor() {
    super();
    // Créer un "shadow" DOM isolé
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.shadowRoot.innerHTML = `
      <style>
        /* Ces styles ne fuient pas vers l'extérieur */
        button {
          background: var(--color-primary);
          color: white;
          border: none;
          padding: 8px 16px;
          border-radius: 8px;
          cursor: pointer;
        }
        button:hover {
          opacity: 0.9;
        }
      </style>
      <button><slot></slot></button>
    `;
  }
}

customElements.define('ag-button', AgButton);
```

Usage :
```html
<ag-button>Cliquez-moi</ag-button>
```

### 3. HTML Templates

Définir des structures réutilisables :

```html
<template id="toast-template">
  <div class="toast">
    <span class="toast-icon"></span>
    <span class="toast-message"></span>
    <button class="toast-close">×</button>
  </div>
</template>

<script>
class AgToast extends HTMLElement {
  connectedCallback() {
    const template = document.getElementById('toast-template');
    const clone = template.content.cloneNode(true);

    clone.querySelector('.toast-message').textContent =
      this.getAttribute('message');

    this.appendChild(clone);
  }
}
customElements.define('ag-toast', AgToast);
</script>
```

---

## Avantages pour AG-VOTE

| Aspect | Avant (HTML/JS) | Après (Web Components) |
|--------|-----------------|------------------------|
| **Réutilisabilité** | Copier-coller HTML | Un seul `<ag-card>` |
| **Encapsulation** | Styles globaux conflictuels | Shadow DOM isolé |
| **Maintenance** | Modifier 22 fichiers | Modifier 1 composant |
| **Testabilité** | Difficile | Composant unitaire |
| **Framework** | Aucun requis | Natif navigateur |

---

## Composants implémentés

Les composants suivants sont disponibles dans `public/assets/js/components/` :

### 1. `<ag-kpi>` — Carte KPI

```html
<ag-kpi value="42" label="Présents" variant="success" icon="users"></ag-kpi>
```

**Attributs** : `value`, `label`, `variant` (success/warning/danger/primary), `icon`, `size` (sm/md/lg)

### 2. `<ag-badge>` — Badge de statut

```html
<ag-badge variant="live">En cours</ag-badge>
<ag-badge variant="success">Validé</ag-badge>
<ag-badge variant="draft">Brouillon</ag-badge>
```

**Variantes** : success, warning, danger, live, draft, info, muted

### 3. `<ag-spinner>` — Indicateur de chargement

```html
<ag-spinner size="md"></ag-spinner>
<ag-spinner size="lg" variant="primary"></ag-spinner>
```

**Attributs** : `size` (sm/md/lg/xl), `variant` (primary/muted)

### 4. `<ag-toast>` — Notifications toast

```html
<!-- Utilisation programmatique (recommandée) -->
<script>
AgToast.show('success', 'Vote enregistré !');
AgToast.show('error', 'Erreur de connexion');
AgToast.show('info', 'Chargement en cours...', 10000); // 10s
</script>
```

**Types** : success, error, warning, info

### 5. `<ag-quorum-bar>` — Barre de progression quorum

```html
<ag-quorum-bar value="67" threshold="50" label="Quorum atteint"></ag-quorum-bar>
```

**Attributs** : `value` (0-100), `threshold`, `label`, `show-percentage`

### 6. `<ag-vote-button>` — Boutons de vote

```html
<ag-vote-button value="for">Pour</ag-vote-button>
<ag-vote-button value="against">Contre</ag-vote-button>
<ag-vote-button value="abstain" selected>Abstention</ag-vote-button>
```

**Attributs** : `value` (for/against/abstain/nsp), `selected`, `disabled`, `size` (md/lg/xl)
**Événement** : `ag-vote` (detail: { value })

---

## Utilisation

### Charger tous les composants

```html
<script type="module" src="/assets/js/components/index.js"></script>
```

### Import individuel

```html
<script type="module">
  import '/assets/js/components/ag-kpi.js';
  import '/assets/js/components/ag-badge.js';
</script>
```

---

## Architecture

### Structure des fichiers

```
public/assets/js/components/
├── index.js          # Point d'entrée - importe tous les composants
├── ag-kpi.js         # Cartes KPI
├── ag-badge.js       # Badges de statut
├── ag-spinner.js     # Indicateurs de chargement
├── ag-toast.js       # Notifications toast
├── ag-quorum-bar.js  # Barres de progression quorum
└── ag-vote-button.js # Boutons de vote
```

### Exemple : ag-kpi.js

```javascript
/**
 * AG-VOTE KPI Component
 * Usage: <ag-kpi value="42" label="Présents" variant="success"></ag-kpi>
 */
class AgKpi extends HTMLElement {
  static get observedAttributes() {
    return ['value', 'label', 'variant'];
  }

  constructor() {
    super();
    this.attachShadow({ mode: 'open' });
  }

  connectedCallback() {
    this.render();
  }

  attributeChangedCallback() {
    this.render();
  }

  render() {
    const value = this.getAttribute('value') || '0';
    const label = this.getAttribute('label') || '';
    const variant = this.getAttribute('variant') || 'default';

    this.shadowRoot.innerHTML = `
      <style>
        :host {
          display: block;
        }
        .kpi {
          background: var(--color-surface, #fff);
          border: 1px solid var(--color-border, #e5e5e5);
          border-radius: 12px;
          padding: 1.25rem;
          text-align: center;
        }
        .kpi-value {
          font-size: 2.25rem;
          font-weight: 800;
          line-height: 1;
        }
        .kpi-label {
          font-size: 0.875rem;
          color: var(--color-text-muted, #666);
          margin-top: 0.5rem;
        }
        /* Variants */
        :host([variant="success"]) .kpi-value { color: var(--color-success, #22c55e); }
        :host([variant="warning"]) .kpi-value { color: var(--color-warning, #f59e0b); }
        :host([variant="danger"]) .kpi-value { color: var(--color-danger, #ef4444); }
        :host([variant="primary"]) .kpi-value { color: var(--color-primary, #3b82f6); }
      </style>
      <div class="kpi">
        <div class="kpi-value">${value}</div>
        <div class="kpi-label">${label}</div>
      </div>
    `;
  }
}

customElements.define('ag-kpi', AgKpi);

export default AgKpi;
```

### Exemple d'intégration dans une page

```html
<!DOCTYPE html>
<html lang="fr">
<head>
  <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
  <!-- Charger les composants -->
  <script type="module" src="/assets/js/components/index.js"></script>

  <!-- Utiliser -->
  <div class="kpi-grid">
    <ag-kpi value="45" label="Présents" variant="success" icon="users"></ag-kpi>
    <ag-kpi value="67%" label="Quorum" variant="primary"></ag-kpi>
    <ag-kpi value="3" label="Procurations"></ag-kpi>
  </div>

  <ag-quorum-bar value="67" threshold="50" label="Quorum"></ag-quorum-bar>

  <div class="vote-buttons">
    <ag-vote-button value="for">Pour</ag-vote-button>
    <ag-vote-button value="against">Contre</ag-vote-button>
    <ag-vote-button value="abstain">Abstention</ag-vote-button>
  </div>

  <script>
    // Écouter les votes
    document.addEventListener('ag-vote', (e) => {
      console.log('Vote:', e.detail.value);
      AgToast.show('success', `Vote "${e.detail.value}" enregistré`);
    });
  </script>
</body>
</html>
```

---

## Compatibilité navigateurs

| Navigateur | Support |
|------------|---------|
| Chrome 67+ | ✅ Complet |
| Firefox 63+ | ✅ Complet |
| Safari 13+ | ✅ Complet |
| Edge 79+ | ✅ Complet |
| IE 11 | ❌ Non supporté |

**Note** : AG-VOTE cible des navigateurs modernes, donc pas de polyfill nécessaire.

---

## Ressources

- [MDN Web Components Guide](https://developer.mozilla.org/en-US/docs/Web/API/Web_components)
- [web.dev Custom Elements](https://web.dev/articles/custom-elements-v1)
- [Lit (librairie légère)](https://lit.dev/) — Optionnel, simplifie l'écriture

---

## Conclusion

Les Web Components d'AG-VOTE apportent :

1. **Réutilisabilité** — Composants utilisables dans toutes les pages
2. **Encapsulation** — Styles isolés via Shadow DOM
3. **Standardisation** — Interface cohérente garantie
4. **Maintenance simplifiée** — Modifier un fichier impacte toutes les pages

### Composants disponibles

| Composant | Fichier | Statut |
|-----------|---------|--------|
| `<ag-kpi>` | ag-kpi.js | ✅ Implémenté |
| `<ag-badge>` | ag-badge.js | ✅ Implémenté |
| `<ag-spinner>` | ag-spinner.js | ✅ Implémenté |
| `<ag-toast>` | ag-toast.js | ✅ Implémenté |
| `<ag-quorum-bar>` | ag-quorum-bar.js | ✅ Implémenté |
| `<ag-vote-button>` | ag-vote-button.js | ✅ Implémenté |

### Prochaines étapes

- Migration progressive des pages existantes pour utiliser les composants
- Création de `<ag-modal>` et `<ag-drawer>` si nécessaire
- Documentation des patterns d'événements
