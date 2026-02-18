# AUDIT UX/UI — AG-Vote

> Audit réalisé par lecture du code source. Chaque problème est vérifié dans le code.
> Date : 2026-02-18

## Tableau de synthèse — Tous problèmes par priorité

| Rang | ID | Persona | Problème | Sévérité | Effort | Statut |
|------|-----|---------|----------|----------|--------|--------|
| 1 | P3-1 | Opérateur | Ouverture vote sans confirmation | Critique | Faible | DONE |
| 2 | P4-1 | Votants | Vote "Blanc" absent de l'interface tablette | Critique | Moyen | DONE |
| 3 | P2-1 | Président | Aucune indication du rôle connecté | Critique | Faible | DONE |
| 4 | P2-2 | Président | Boutons désactivés sans explication | Critique | Faible | DONE |
| 5 | P7-1 | Admin | Reset démo trop facile à déclencher | Critique | Faible | DONE |
| 6 | P7-2 | Admin | Aucun log des actions admin | Critique | Moyen | TODO |
| 7 | P3-2 | Opérateur | Vote unanime irréversible (batch sans rollback) | Haute | Moyen | DONE |
| 8 | P3-3 | Opérateur | Vote manuel justification non éditable | Haute | Faible | DONE |
| 9 | P3-4 | Opérateur | Pas d'annulation de vote manuel | Haute | Moyen | TODO |
| 10 | P4-2 | Votants | Raccourcis clavier 1/2/3 affichés mais inopérants | Haute | Faible | DONE |
| 11 | P4-3 | Votants | Pas de queue offline pour les votes | Haute | Élevé | TODO |
| 12 | P4-4 | Votants | Procuration sans indication claire | Haute | Faible | DONE |
| 13 | P2-3 | Président | "Clôturer le vote" vs "Clôturer la séance" confusion | Haute | Faible | DONE |
| 14 | P2-4 | Président | Clôture scrutin sans confirmation modale | Haute | Faible | DONE |
| 15 | P5-1 | Post-séance | Validation irréversible sans confirmation forte | Haute | Moyen | DONE |
| 16 | P6-1 | Auditeur | Anomalies sans liens de drill-down | Haute | Moyen | TODO |
| 17 | P6-2 | Auditeur | Log d'audit non filtrable | Haute | Moyen | DONE |
| 18 | P7-3 | Admin | Création user sans validation inline | Haute | Moyen | DONE |
| 19 | P3-5 | Opérateur | Grille vote manuelle sans recherche | Moyenne | Faible | DONE |
| 20 | P3-6 | Opérateur | Pas de refresh auto sur erreur API | Moyenne | Faible | DONE |
| 21 | P4-5 | Votants | Erreurs 409 non gérées côté front | Moyenne | Moyen | DONE |
| 22 | P4-6 | Votants | Overlay blocage sans contact/aide | Moyenne | Faible | DONE |
| 23 | P4-7 | Votants | Pas d'avertissement "vote irréversible" | Moyenne | Faible | DONE |
| 24 | P2-5 | Président | Pas de proclamation explicite des résultats | Moyenne | Moyen | TODO |
| 25 | P2-7 | Président | Page validation sans contexte de séance | Moyenne | Faible | DONE |
| 26 | P5-2 | Post-séance | Regex nom président trop strict | Moyenne | Faible | DONE |
| 27 | P5-3 | Post-séance | Checklist sans liens de remédiation | Moyenne | Moyen | DONE |
| 28 | P5-4 | Post-séance | Exports sans feedback | Moyenne | Faible | DONE |
| 29 | P6-3 | Auditeur | Hash d'intégrité sans explication | Moyenne | Faible | DONE |
| 30 | P6-4 | Auditeur | Sévérité anomalies par couleur seulement | Moyenne | Faible | DONE |
| 31 | P6-5 | Auditeur | Auto-refresh perd les filtres | Moyenne | Faible | DONE |
| 32 | P7-4 | Admin | Assignation rôles un par un | Moyenne | Moyen | TODO |
| 33 | P7-5 | Admin | Éditeur templates sans coloration | Moyenne | Élevé | TODO |
| 34 | P7-6 | Admin | prompt() natif pour duplication | Moyenne | Faible | TODO |
| 35 | P2-6 | Président | Onglets inutiles visibles | Basse | Faible | DONE |
| 36 | P3-7 | Opérateur | File parole non réordonnable | Basse | Moyen | TODO |
| 37 | P3-8 | Opérateur | Timer parole côté client | Basse | Moyen | TODO |
| 38 | P4-8 | Votants | Reçu vote non persistant | Basse | Faible | TODO |
| 39 | P5-5 | Post-séance | Pas de dé-archivage admin | Basse | Élevé | TODO |
| 40 | P6-6 | Auditeur | Export audit texte brut seulement | Basse | Moyen | TODO |
| 41 | P7-7 | Admin | Machine à états : visualisation confuse | Basse | Moyen | TODO |
| 42 | P7-8 | Admin | KPI santé sans seuils documentés | Basse | Faible | DONE |

---

## Détail par persona

### Persona 2 — Le Président de séance

Le Président n'a pas d'interface dédiée. Il partage la console opérateur (`operator.htmx.html`) avec des restrictions de permissions silencieuses.

- **P2-1** Aucune indication du rôle connecté — Pas de badge "Président" dans le header
- **P2-2** Boutons désactivés sans explication — Grisés sans tooltip ni aria-label
- **P2-3** "Clôturer le vote" vs "Clôturer la séance" — Même style, même verbe, conséquences différentes
- **P2-4** Clôture scrutin sans confirmation modale — `confirm()` simple
- **P2-5** Pas de proclamation explicite des résultats — Broadcast silencieux via WebSocket
- **P2-6** Onglets inutiles visibles — "Paramètres" visible pour le président
- **P2-7** Page validation sans contexte de séance — Seul champ : "Nom du président"

### Persona 3 — L'Opérateur

Protections techniques solides (double-click, locks DB, audit trail), mais lacunes de confirmation sur actions critiques.

- **P3-1** Ouverture vote sans confirmation — Misclick déclenche un vote, pas de "Ouvrir ce vote ?"
- **P3-2** Vote unanime irréversible — Batch sans rollback, `confirm()` simple
- **P3-3** Vote manuel justification non éditable — Hardcodée "Vote opérateur manuel"
- **P3-4** Pas d'annulation de vote manuel — Contrainte UNIQUE, pas de bouton Annuler
- **P3-5** Grille vote manuelle sans recherche — Scroll obligatoire pour 100+ membres
- **P3-6** Pas de refresh auto sur erreur API — UI incohérente après erreur 409
- **P3-7** File parole non réordonnable — FIFO uniquement, pas de drag-drop
- **P3-8** Timer parole côté client — setInterval JS, pas de sync serveur

### Persona 4 — Les Votants

Interface bien conçue pour tablette (ARIA, live regions, focus trap), lacunes sur vote blanc et mode hors ligne.

- **P4-1** Vote "Blanc" absent de l'interface tablette — Seuls Pour/Contre/Abstention
- **P4-2** Raccourcis clavier 1/2/3 affichés mais inopérants — Badges visuels sans listener
- **P4-3** Pas de queue offline — Boutons désactivés si hors ligne
- **P4-4** Procuration sans indication claire — Pas de "Vous votez pour X avec N voix"
- **P4-5** Erreurs 409 non gérées côté front — Texte brut, pas de page user-friendly
- **P4-6** Overlay blocage sans contact/aide — Écran noir sans action possible
- **P4-7** Pas d'avertissement "vote irréversible" — Overlay ne le précise pas
- **P4-8** Reçu vote non persistant — Disparaît au refresh

### Persona 5 — Le Responsable post-séance

Workflow de validation solide mais cognitvement lourd et sans recours.

- **P5-1** Validation irréversible sans 2ème confirmation forte — Checkbox + bouton seulement
- **P5-2** Regex nom président trop strict — Refuse "Jr.", "III", initiales
- **P5-3** Checklist sans liens de remédiation — Pas de lien pour corriger les problèmes
- **P5-4** Exports sans feedback — Téléchargement silencieux
- **P5-5** Pas de dé-archivage admin — One-way, pas de correction possible

### Persona 6 — L'Auditeur

10 checks de cohérence et détection d'anomalies. Manque d'investigation et de drill-down.

- **P6-1** Anomalies sans liens de navigation — Texte sans lien vers membre/vote
- **P6-2** Log d'audit non filtrable — 50 derniers événements sans filtre
- **P6-3** Hash d'intégrité sans explication — Affiché brut sans contexte
- **P6-4** Sévérité par couleur uniquement — Non accessible WCAG
- **P6-5** Auto-refresh perd les filtres — Polling réinitialise la sélection
- **P6-6** Export rapport texte brut seulement — Pas de PDF/JSON structuré

### Persona 7 — L'Administrateur système

Interface complète (6 onglets) mais sans garde-fous suffisants et sans audit des actions admin.

- **P7-1** Reset démo trop facile — Un seul clic sans modale forte
- **P7-2** Aucun log des actions admin — Création/suppression users non tracée
- **P7-3** Création user sans validation inline — Pas d'indicateur force MDP
- **P7-4** Assignation rôles un par un — Pas de bulk
- **P7-5** Éditeur templates sans coloration syntaxique — textarea simple
- **P7-6** Duplication template via prompt() natif — Non stylé
- **P7-7** Machine à états : visualisation confuse — Pas de vraies flèches
- **P7-8** KPI santé sans seuils documentés — Points colorés sans explication
