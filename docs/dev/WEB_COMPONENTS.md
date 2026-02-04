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

## Composants candidats pour AG-VOTE

### Priorité haute (usage fréquent)

1. **`<ag-kpi>`** — Carte KPI (présents, quorum, votes)
   ```html
   <ag-kpi value="42" label="Présents" variant="success"></ag-kpi>
   ```

2. **`<ag-badge>`** — Badge de statut
   ```html
   <ag-badge variant="live">En cours</ag-badge>
   ```

3. **`<ag-toast>`** — Notification toast
   ```html
   <ag-toast type="success" message="Vote enregistré"></ag-toast>
   ```

4. **`<ag-modal>`** — Modale/Dialog
   ```html
   <ag-modal title="Confirmer" open>
     <p>Êtes-vous sûr ?</p>
   </ag-modal>
   ```

### Priorité moyenne

5. **`<ag-quorum-bar>`** — Barre de progression quorum
6. **`<ag-vote-button>`** — Bouton de vote (Pour/Contre/Abstention)
7. **`<ag-member-card>`** — Carte membre
8. **`<ag-motion-card>`** — Carte résolution

---

## Implémentation progressive

### Étape 1 : Créer le fichier de composants

```
public/assets/js/components/
├── ag-kpi.js
├── ag-badge.js
├── ag-toast.js
└── index.js  # Export tous les composants
```

### Étape 2 : Exemple `ag-kpi.js`

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

### Étape 3 : Utiliser dans une page

```html
<!-- Charger les composants -->
<script type="module" src="/assets/js/components/ag-kpi.js"></script>

<!-- Utiliser -->
<div class="kpi-grid">
  <ag-kpi value="45" label="Présents" variant="success"></ag-kpi>
  <ag-kpi value="67%" label="Quorum" variant="primary"></ag-kpi>
  <ag-kpi value="3" label="Procurations"></ag-kpi>
</div>
```

---

## Migration progressive

### Phase 1 : Composants "leaf" (sans enfants)
- `<ag-kpi>`, `<ag-badge>`, `<ag-spinner>`
- Impact minimal, facile à tester

### Phase 2 : Composants interactifs
- `<ag-toast>`, `<ag-vote-button>`
- Gèrent des événements

### Phase 3 : Composants complexes
- `<ag-modal>`, `<ag-drawer>`
- Utilisent des slots pour le contenu

### Phase 4 : Composants composés
- `<ag-motion-card>` qui utilise `<ag-badge>` et `<ag-vote-button>`
- Composition de composants

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

Les Web Components permettent de :
1. **Réduire la duplication** — Un composant, plusieurs usages
2. **Encapsuler les styles** — Pas de conflits CSS
3. **Standardiser l'interface** — Cohérence visuelle garantie
4. **Faciliter la maintenance** — Modifier un fichier, pas 22

Pour AG-VOTE, commencer par `<ag-kpi>` et `<ag-badge>` serait un bon point de départ pour évaluer l'approche.
