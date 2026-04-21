# Requirements: AG-VOTE v1.9

**Defined:** 2026-04-21
**Core Value:** L'application doit etre fiable en production — aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v1.9 Requirements

Requirements pour le milestone UX Standards & Retention. Chaque requirement est testable et oriente utilisateur.

### Navigation

- [ ] **NAV-01**: Sidebar toujours ouverte ~200px avec labels visibles, plus de hover-to-expand ni rail d'icones
- [ ] **NAV-02**: Items de navigation filtres par role — un votant ne voit que "Voter", pas 16 liens
- [ ] **NAV-03**: Tous les boutons et liens de navigation font minimum 44x44px (WCAG 2.5.8)
- [ ] **NAV-04**: Page d'accueil = carte centree avec logo AG-VOTE + formulaire de connexion

### Typographie & Spacing

- [ ] **TYPO-01**: Taille de police de base passe de 14px a 16px sur desktop et mobile
- [ ] **TYPO-02**: Labels de formulaire en casse normale (plus d'UPPERCASE), couleur lisible (plus de muted)
- [ ] **TYPO-03**: Header passe de 56px a 64px, contenu aere (breadcrumb + titre sans sous-titre ni barre deco)
- [ ] **TYPO-04**: Espacement entre elements de formulaire et sections passe de 14px a 20-24px

### Feedback & Etats

- [ ] **FEED-01**: Chaque liste/grille affiche un message clair quand vide ("Creez votre premiere seance") au lieu de skeletons suspendus
- [ ] **FEED-02**: Apres un vote, confirmation persistante visible (pas un flash 3s) avec horodatage
- [ ] **FEED-03**: Filtres et recherches affichent "Aucun resultat" avec suggestion de reinitialiser les filtres
- [ ] **FEED-04**: Indicateur de chargement explicite en francais ("Chargement...") au lieu de skeletons silencieux

### Clarte & Jargon

- [ ] **CLAR-01**: L'interface votant n'affiche aucun terme technique (eIDAS, SHA-256, quorum, CNIL)
- [ ] **CLAR-02**: Les termes techniques cote admin/operateur ont des tooltips explicatifs en francais
- [ ] **CLAR-03**: Le pattern "tapez VALIDER" est remplace par un modal avec checkbox + bouton Confirmer
- [ ] **CLAR-04**: Chaque bouton d'export a une description d'une ligne expliquant le contenu du fichier

## v2 Requirements

Deferred to future release.

### Operateur

- **OP-01**: Checklist operateur en mode live (quorum OK, reseau OK, votes recus)
- **OP-02**: Reduction des zones d'info de 9 a 4-5 en mode execution
- **OP-03**: Animation sur les compteurs de vote en temps reel

### Affichage Public

- **PUB-01**: Taille de texte adaptee a la distance (labels 24px+, pourcentages 40px+)
- **PUB-02**: Animation sur les barres de resultats quand les votes arrivent
- **PUB-03**: Etat par defaut "Seance a venir" au lieu de "Aucune seance"

## Out of Scope

| Feature | Reason |
|---------|--------|
| Nouvelles fonctionnalites metier | Stabiliser l'UX d'abord |
| Migration framework (Symfony/Laravel) | Refactoring incremental uniquement |
| PDFs de convocation/emargement | Hors perimetre de l'app |
| Raccourcis clavier | Hors perimetre |
| Refonte operateur complete | Trop large pour ce milestone — v2 |
| Refonte affichage public | Trop large pour ce milestone — v2 |
| Visual regression testing | Milestone dedie |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| NAV-01 | — | Pending |
| NAV-02 | — | Pending |
| NAV-03 | — | Pending |
| NAV-04 | — | Pending |
| TYPO-01 | — | Pending |
| TYPO-02 | — | Pending |
| TYPO-03 | — | Pending |
| TYPO-04 | — | Pending |
| FEED-01 | — | Pending |
| FEED-02 | — | Pending |
| FEED-03 | — | Pending |
| FEED-04 | — | Pending |
| CLAR-01 | — | Pending |
| CLAR-02 | — | Pending |
| CLAR-03 | — | Pending |
| CLAR-04 | — | Pending |

**Coverage:**
- v1.9 requirements: 16 total
- Mapped to phases: 0
- Unmapped: 16

---
*Requirements defined: 2026-04-21*
*Last updated: 2026-04-21 after initial definition*
