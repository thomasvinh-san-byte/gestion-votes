# Plan de Refactoring AG-Vote - Inspiré de Helios

## Objectif
Centraliser le workflow opérateur dans `operator.htmx.html` pour permettre de gérer une séance de A à Z depuis une seule page, comme le fait Helios avec son admin dashboard.

---

## État Actuel vs. État Cible

### Workflow Actuel (fragmenté)
```
meetings.htmx.html     → Créer séance
members.htmx.html      → Gérer membres (global, pas par séance)
attendance.htmx.html   → Pointer présences
motions.htmx.html      → Gérer résolutions
operator.htmx.html     → Transitions + votes
validate.htmx.html     → Valider séance
report.htmx.html       → Générer PV
```

### Workflow Cible (centralisé)
```
meetings.htmx.html     → Liste + Créer séance (unique fonction)
operator.htmx.html     → TOUT le reste :
  ├── [Tab/Section] Membres      → Vue + Import CSV + Ajout rapide
  ├── [Tab/Section] Présences    → Pointage inline
  ├── [Tab/Section] Résolutions  → CRUD inline
  ├── [Drawer] Rôles             → Président, Assesseurs (existe)
  ├── [Drawer] Réglages          → Politiques quorum/vote (existe)
  ├── [Drawer] Incidents         → Déclaration (existe)
  ├── [Section] Checklist        → Pré-requis avant freeze (existe)
  ├── [Section] Transitions      → Boutons d'état (existe)
  ├── [Section] Vote Live        → Panel de vote manuel (existe)
  └── [Section] Exports          → PV + CSV (après validation)
```

---

## Chemin Critique - Ordre d'Implémentation

### Phase 0: Corrections P0 ✅ FAIT
- [x] AttendanceRepository::upsert tenant_id
- [x] JOIN tenant-safe
- [x] Mode 'excused' dans enum
- [x] Président optionnel pour démo
- [x] Auth bypass pour dev (auth=0)

### Phase 1: Centraliser la Préparation dans Operator

**1.1 Section Membres Inline**
```
┌─────────────────────────────────────────────────┐
│ 👥 Membres                            [+ Ajouter]│
├─────────────────────────────────────────────────┤
│ Total: 45 membres | Poids total: 1000           │
│ ┌─ [Drawer: Import CSV] [Drawer: Liste complète]│
│ │                                                │
│ │ Recherche: [___________]                       │
│ │                                                │
│ │ Nom             Email           Poids   Status │
│ │ ─────────────────────────────────────────────  │
│ │ Jean Dupont     jean@ex.com     10      Actif  │
│ │ Marie Martin    marie@ex.com    15      Actif  │
│ │ ...                                            │
│ └───────────────────────────────────────────────│
└─────────────────────────────────────────────────┘
```

**Fichiers à modifier:**
- `public/operator.htmx.html` - Ajouter section HTML
- `public/assets/js/operator.js` - Ajouter drawer "members"
- Réutiliser `/api/v1/members.php` (existe)
- Réutiliser `/api/v1/members_import_csv.php` (existe)

**1.2 Section Présences Inline**
```
┌─────────────────────────────────────────────────┐
│ ✓ Présences                    [Tous présents]  │
├─────────────────────────────────────────────────┤
│ Présents: 35 | Distants: 5 | Excusés: 3 | Abs: 2│
│                                                 │
│ Recherche: [___________]     Mode: [Présent ▼]  │
│                                                 │
│ ☑ Jean Dupont      [Présent] [Distant] [Excusé]│
│ ☑ Marie Martin     [Présent] [Distant] [Excusé]│
│ ☐ Paul Bernard     [Présent] [Distant] [Excusé]│
│ ...                                             │
└─────────────────────────────────────────────────┘
```

**Fichiers à modifier:**
- `public/operator.htmx.html` - Ajouter section présences
- `public/assets/js/operator.js` - Intégrer logique de attendance.js
- API existantes suffisantes

**1.3 Section Résolutions Inline (amélioration)**
```
┌─────────────────────────────────────────────────┐
│ 📋 Résolutions                       [+ Créer]  │
├─────────────────────────────────────────────────┤
│ 3 résolutions | 1 votée | 2 en attente          │
│                                                 │
│ #  Titre                      Status    Actions │
│ ── ─────────────────────────  ────────  ─────── │
│ 1  Approbation budget 2025    ✓ Voté    [Voir]  │
│ 2  Élection conseil           ○ Attente [Ouvrir]│
│ 3  Modification statuts       ○ Attente [Éditer]│
│                                                 │
│ [+ Ajouter résolution]                          │
└─────────────────────────────────────────────────┘
```

**Ajouter:**
- Modal/drawer pour créer résolution inline
- Édition inline du titre

---

### Phase 2: Validations Pré-Freeze (Inspiré Helios)

**Helios `issues_before_freeze()`:**
```python
def issues_before_freeze(self):
    issues = []
    if not self.questions_verified:
        issues.append("Questions not defined")
    if not self.trustees_with_public_keys:
        issues.append("Trustees without public keys")
    if self.voter_count == 0:
        issues.append("No voters")
    return issues
```

**AG-Vote `issues_before_transition(to_status)`:**
```php
// Nouveau: /app/services/MeetingWorkflowService.php

public static function issuesBeforeTransition(string $meetingId, string $toStatus): array
{
    $issues = [];

    // Pour draft → scheduled
    if ($toStatus === 'scheduled') {
        if (!self::hasMotions($meetingId)) {
            $issues[] = ['code' => 'no_motions', 'msg' => 'Aucune résolution créée'];
        }
    }

    // Pour scheduled → frozen
    if ($toStatus === 'frozen') {
        if (!self::hasAttendance($meetingId)) {
            $issues[] = ['code' => 'no_attendance', 'msg' => 'Aucune présence pointée'];
        }
        // Optionnel pour démo:
        // if (!self::hasPresident($meetingId)) { ... }
    }

    // Pour frozen → live
    if ($toStatus === 'live') {
        if (!self::quorumMet($meetingId)) {
            $issues[] = ['code' => 'quorum_not_met', 'msg' => 'Quorum non atteint', 'warning' => true];
        }
    }

    // Pour live → closed
    if ($toStatus === 'closed') {
        if (self::hasOpenMotion($meetingId)) {
            $issues[] = ['code' => 'motion_open', 'msg' => 'Une résolution est encore ouverte'];
        }
    }

    // Pour closed → validated
    if ($toStatus === 'validated') {
        if (!self::allMotionsClosed($meetingId)) {
            $issues[] = ['code' => 'motions_not_closed', 'msg' => 'Résolutions non clôturées'];
        }
    }

    return $issues;
}
```

**Fichiers à créer/modifier:**
- `app/services/MeetingWorkflowService.php` (nouveau)
- `public/api/v1/meeting_transition.php` - Appeler validation
- `public/assets/js/operator.js` - Afficher issues avant transition

---

### Phase 3: Exports Post-Validation

**Section exports (visible uniquement si status = validated|archived):**
```
┌─────────────────────────────────────────────────┐
│ 📄 Procès-Verbal & Exports                      │
├─────────────────────────────────────────────────┤
│ ✓ Séance validée le 03/02/2026 à 14:30          │
│                                                 │
│ [📄 Télécharger PV (PDF)]                       │
│ [📊 Export Présences (CSV)]                     │
│ [📊 Export Votes (CSV)]                         │
│ [📧 Envoyer PV par email]                       │
└─────────────────────────────────────────────────┘
```

---

### Phase 4: Simplification Navigation

**Sidebar simplifiée:**
```
┌──────────────────────┐
│ AG-VOTE              │
├──────────────────────┤
│ 📋 Séances           │ → meetings.htmx.html (liste + créer)
│ 🎯 Fiche Séance      │ → operator.htmx.html (tout le reste)
├──────────────────────┤
│ ⚙️ Administration    │
│   └─ 👥 Membres      │ → members.htmx.html (gestion globale)
│   └─ 📜 Politiques   │ → admin.htmx.html
├──────────────────────┤
│ 📦 Archives          │ → archives.htmx.html
└──────────────────────┘
```

---

## Estimation Effort

| Phase | Complexité | Fichiers | Priorité |
|-------|------------|----------|----------|
| 1.1 Membres inline | Moyenne | 2 | P1 |
| 1.2 Présences inline | Haute | 2 | P1 |
| 1.3 Résolutions inline | Basse | 2 | P2 |
| 2 Validations | Moyenne | 3 | P1 |
| 3 Exports | Basse | 1 | P2 |
| 4 Navigation | Basse | 2 | P3 |

---

## API Existantes Réutilisables

| Endpoint | Usage |
|----------|-------|
| `GET /api/v1/members.php` | Liste membres tenant |
| `POST /api/v1/members.php` | Créer membre |
| `POST /api/v1/members_import_csv.php` | Import CSV |
| `GET /api/v1/attendances.php?meeting_id=X` | Liste présences |
| `POST /api/v1/attendances_upsert.php` | Modifier présence |
| `POST /api/v1/attendances_bulk.php` | Bulk présences |
| `GET /api/v1/motions_for_meeting.php?meeting_id=X` | Liste résolutions |
| `POST /api/v1/motions.php` | Créer résolution |
| `POST /api/v1/meeting_transition.php` | Changer état |
| `GET /api/v1/wizard_status.php?meeting_id=X` | État checklist |

---

## Prochaines Étapes

1. **Valider ce plan** avec vous
2. **Phase 1.2** (Présences inline) - Plus critique pour workflow
3. **Phase 2** (Validations) - Sécurise les transitions
4. **Phase 1.1** (Membres inline) - Confort opérateur
5. Tests end-to-end du workflow complet
