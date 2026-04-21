# Requirements: AG-VOTE v2.0

**Defined:** 2026-04-21
**Core Value:** L'application doit etre fiable en production -- aucun crash lie a des fallbacks fichiers, des fuites memoire, ou des timeouts silencieux.

## v2.0 Requirements

Requirements pour le milestone Operateur Live UX. Chaque requirement est testable et oriente utilisateur.

### Checklist Operateur

- [ ] **CHECK-01**: En mode live, une checklist affiche le statut quorum (atteint/non atteint) avec le ratio votants/total
- [ ] **CHECK-02**: La checklist indique le nombre de votes recus en temps reel via SSE
- [ ] **CHECK-03**: La checklist montre le statut connexion reseau et SSE (connecte/deconnecte)
- [ ] **CHECK-04**: La checklist affiche le nombre de votants connectes en temps reel
- [ ] **CHECK-05**: Si un indicateur passe au rouge (quorum non atteint, SSE deconnecte), une alerte visuelle automatique apparait

### Interface Epuree

- [ ] **FOCUS-01**: En mode execution, l'interface operateur se reduit a 5 zones: titre motion, resultat vote, quorum status, chronometre, actions
- [ ] **FOCUS-02**: Les boutons d'action (lancer vote, fermer scrutin, passer motion) sont visibles dans la vue epuree
- [ ] **FOCUS-03**: L'operateur peut basculer entre vue complete et vue focus via un toggle visible

### Animations Vote

- [ ] **ANIM-01**: Les compteurs de vote (pour/contre/abstention) s'animent en temps reel quand un vote arrive via SSE
- [ ] **ANIM-02**: Les barres de progression des resultats s'animent en transition fluide (pas de saut brusque)
- [ ] **ANIM-03**: L'animation respecte prefers-reduced-motion (desactivee si l'utilisateur le demande)

## Future Requirements

Deferred to next milestone.

### Affichage Public

- **PUB-01**: Taille de texte adaptee a la distance (labels 24px+, pourcentages 40px+)
- **PUB-02**: Animation sur les barres de resultats quand les votes arrivent
- **PUB-03**: Etat par defaut "Seance a venir" au lieu de "Aucune seance"

## Out of Scope

| Feature | Reason |
|---------|--------|
| Refonte affichage public | Trop large — milestone dedie PUB-01..03 |
| Nouvelles fonctionnalites metier | Focus sur UX operateur |
| Migration framework | Refactoring incremental uniquement |
| PDFs de convocation/emargement | Hors perimetre de l'app |
| Raccourcis clavier | Hors perimetre |

## Traceability

| Requirement | Phase | Status |
|-------------|-------|--------|
| CHECK-01 | TBD | Pending |
| CHECK-02 | TBD | Pending |
| CHECK-03 | TBD | Pending |
| CHECK-04 | TBD | Pending |
| CHECK-05 | TBD | Pending |
| FOCUS-01 | TBD | Pending |
| FOCUS-02 | TBD | Pending |
| FOCUS-03 | TBD | Pending |
| ANIM-01 | TBD | Pending |
| ANIM-02 | TBD | Pending |
| ANIM-03 | TBD | Pending |

**Coverage:**
- v2.0 requirements: 11 total
- Mapped to phases: 0 (pending roadmap)
- Unmapped: 11

---
*Requirements defined: 2026-04-21*
