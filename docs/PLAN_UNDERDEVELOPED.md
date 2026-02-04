# Systèmes sous-développés et améliorations prioritaires

Ce document identifie les fonctionnalités manquantes ou sous-développées dans AG-Vote et propose un plan de développement priorisé.

---

## Vue d'ensemble

Suite à l'audit fonctionnel complet, l'application AG-Vote est **production-ready** pour les cas d'usage principaux (séances de vote formelles). Cependant, plusieurs axes d'amélioration ont été identifiés pour enrichir l'expérience utilisateur et couvrir des cas d'usage avancés.

### Classement des lacunes

| Priorité | Domaine | Impact | Effort |
|----------|---------|--------|--------|
| P1 | Consolidation UX opérateur | Élevé | Moyen |
| P1 | Groupes de membres | Élevé | Faible |
| P2 | Calendrier des séances | Moyen | Moyen |
| P2 | Analytics avancés | Moyen | Moyen |
| P2 | Temps réel (WebSocket) | Moyen | Élevé |
| P3 | Multi-langue | Faible | Moyen |
| P3 | Séances récurrentes | Faible | Moyen |
| P3 | Champs personnalisés | Faible | Moyen |
| P4 | Application mobile | Faible | Élevé |
| P4 | Mode hors-ligne | Faible | Élevé |

---

## P1 — Priorité haute

### 1.1 Consolidation UX opérateur

**Problème actuel** :
L'opérateur doit naviguer entre plusieurs pages pour gérer une séance :
- `operator.htmx.html` : Console principale
- `members.htmx.html` : Gestion des membres
- `speaker.htmx.html` : File des orateurs
- Actions dispersées dans les onglets

**Solution proposée** :
Unifier toutes les actions dans une console opérateur unique avec navigation par onglets intégrée.

**Wireframe conceptuel** :

```
┌─────────────────────────────────────────────────────────────────┐
│ AG 2024 - Console Opérateur                     [Live] [Menu]  │
├─────────────────────────────────────────────────────────────────┤
│ [Tableau de bord] [Résolutions] [Présences] [Membres] [Parole] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐ │
│  │     Présents    │  │   Résolutions   │  │    Prochaine    │ │
│  │       42/50     │  │      5/8        │  │   Résolution 6  │ │
│  └─────────────────┘  └─────────────────┘  └─────────────────┘ │
│                                                                 │
│  [Contenu de l'onglet actif]                                   │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Fichiers à modifier** :
- `operator.htmx.html` : Intégrer tous les onglets
- `operator.js` : Unifier la logique
- Supprimer ou rediriger : `members.htmx.html` (contexte séance)

**Effort estimé** : 5 jours

---

### 1.2 Groupes et catégories de membres

**Problème actuel** :
Pas de moyen de regrouper les membres (collèges électoraux, catégories, départements).

**Impact** :
- Impossible de filtrer les présences par groupe
- Pas de quorum par collège
- Rapports non segmentés

**Solution proposée** :

```sql
CREATE TABLE member_groups (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7), -- #FF5733
    sort_order INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, name)
);

CREATE TABLE member_group_assignments (
    member_id UUID NOT NULL REFERENCES members(id) ON DELETE CASCADE,
    group_id UUID NOT NULL REFERENCES member_groups(id) ON DELETE CASCADE,
    PRIMARY KEY (member_id, group_id)
);
```

**Fonctionnalités** :
- CRUD groupes
- Assignation multiple (un membre peut être dans plusieurs groupes)
- Filtrage présences/votes par groupe
- Statistiques par groupe
- Import CSV avec colonne "groupe"

**Effort estimé** : 3 jours

---

## P2 — Priorité moyenne

### 2.1 Calendrier des séances

**Problème actuel** :
Liste des séances en grille, pas de vue calendrier.

**Solution proposée** :
Ajouter une vue calendrier mensuelle/annuelle.

**Implémentation** :
- Bibliothèque légère : FullCalendar (MIT) ou implémentation maison
- Vue mois avec marqueurs colorés par statut
- Clic pour ouvrir la fiche séance
- Filtres par statut

**Fichiers** :
```
public/assets/js/calendar.js
public/meetings.htmx.html (toggle grille/calendrier)
```

**Effort estimé** : 3 jours

---

### 2.2 Analytics et tableaux de bord avancés

**Problème actuel** :
KPIs basiques uniquement (comptages simples).

**Solution proposée** :
Dashboard avec métriques avancées et graphiques.

**Métriques à ajouter** :

| Métrique | Description |
|----------|-------------|
| Taux de participation | % présents sur eligible, évolution |
| Résolutions adoptées/rejetées | Ratio, tendance |
| Temps moyen par vote | Durée ouverture→fermeture |
| Procurations actives | Évolution, plafond |
| Quorum historique | Tendance sur N séances |
| Top votants | Membres les plus actifs |
| Délais de vote | Distribution des temps de réponse |

**Implémentation** :
- Bibliothèque graphiques : Chart.js (MIT, léger)
- Nouvelle page : `analytics.htmx.html`
- API : `GET /api/v1/analytics.php?type=participation&period=year`

**Effort estimé** : 5 jours

---

### 2.3 Temps réel avec WebSocket

**Problème actuel** :
Polling toutes les 5 secondes pour les mises à jour.

**Impact** :
- Charge serveur accrue
- Latence de 5 secondes max
- Pas d'évènements push

**Solution proposée** :
Ajouter un canal WebSocket pour les événements temps réel.

**Architecture** :

```
┌─────────────┐     HTTP/REST      ┌─────────────┐
│   Browser   │◄──────────────────►│   PHP API   │
│             │                     │             │
│             │◄──── WebSocket ────►│  Ratchet /  │
│             │      (wss://)       │  Swoole     │
└─────────────┘                     └─────────────┘
                                           │
                                    ┌──────┴──────┐
                                    │  PostgreSQL │
                                    │   NOTIFY    │
                                    └─────────────┘
```

**Événements WebSocket** :
- `motion.opened` : Résolution ouverte au vote
- `motion.closed` : Résolution fermée
- `attendance.updated` : Changement de présence
- `vote.cast` : Vote enregistré (anonymisé)
- `quorum.changed` : État quorum modifié
- `meeting.transitioned` : Changement d'état séance

**Implémentation** :
- Serveur : Ratchet (PHP WebSocket) ou Swoole
- Client : Reconnexion automatique
- Fallback : Polling si WS indisponible

**Effort estimé** : 8 jours

---

## P3 — Priorité basse

### 3.1 Multi-langue (i18n)

**Problème actuel** :
Interface uniquement en français.

**Solution proposée** :
Système de traduction avec fichiers JSON.

**Structure** :
```
public/assets/locales/
  fr.json
  en.json
  de.json
```

**Implémentation** :
- Wrapper JS : `t('key')` retourne traduction
- API : `GET /api/v1/locale.php?lang=en`
- Détection automatique (Accept-Language)
- Sélecteur de langue dans le header

**Effort estimé** : 5 jours (structure) + 2 jours/langue

---

### 3.2 Séances récurrentes

**Problème actuel** :
Chaque séance est créée manuellement.

**Solution proposée** :
Support des patterns de récurrence (RRULE).

```sql
ALTER TABLE meetings ADD COLUMN recurrence_rule VARCHAR(255);
-- Exemple: FREQ=MONTHLY;BYDAY=2TH (2ème jeudi du mois)

ALTER TABLE meetings ADD COLUMN parent_meeting_id UUID REFERENCES meetings(id);
-- Pour lier les occurrences
```

**Fonctionnalités** :
- Création avec pattern (mensuel, trimestriel, annuel)
- Duplication automatique des résolutions
- Modification série/occurrence
- Affichage dans calendrier

**Effort estimé** : 5 jours

---

### 3.3 Champs personnalisés pour membres

**Problème actuel** :
Schéma fixe (nom, email, pouvoir).

**Solution proposée** :
Champs dynamiques configurables par tenant.

```sql
CREATE TABLE custom_fields (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    entity_type VARCHAR(50) NOT NULL, -- member, meeting, motion
    field_name VARCHAR(50) NOT NULL,
    field_type VARCHAR(20) NOT NULL, -- text, number, date, select, boolean
    options JSONB, -- Pour select: ["Option 1", "Option 2"]
    is_required BOOLEAN DEFAULT false,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, entity_type, field_name)
);

CREATE TABLE custom_field_values (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    field_id UUID NOT NULL REFERENCES custom_fields(id),
    entity_id UUID NOT NULL, -- member_id, meeting_id, etc.
    value JSONB NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(field_id, entity_id)
);
```

**Fonctionnalités** :
- Configuration des champs par admin
- Formulaires dynamiques
- Filtrage et export avec champs custom
- Import CSV avec colonnes custom

**Effort estimé** : 6 jours

---

## P4 — Priorité future

### 4.1 Application mobile native

**Problème actuel** :
Interface web responsive, pas d'app native.

**Solution proposée** :
Application PWA ou React Native.

**Option PWA** (recommandée) :
- Service Worker pour cache
- Manifest pour installation
- Push notifications

**Option React Native** :
- Plus de développement
- Meilleure expérience native
- Store deployment

**Effort estimé** : 15-30 jours selon option

---

### 4.2 Mode hors-ligne

**Problème actuel** :
Connexion internet requise.

**Solution proposée** :
Cache local avec synchronisation.

**Fonctionnalités** :
- Cache des données de séance
- Queue des votes hors-ligne
- Synchronisation au retour réseau
- Indicateur de statut connexion

**Complexité** :
- Conflits de synchronisation
- Validation des votes tardifs
- Sécurité des données locales

**Effort estimé** : 12 jours

---

## Autres améliorations identifiées

### Documentation et API

| Amélioration | Statut | Effort |
|--------------|--------|--------|
| OpenAPI complet | Partiel | 3 jours |
| Documentation développeur | Existant | - |
| Tutoriels vidéo | Absent | 5 jours |
| Guide utilisateur PDF | Absent | 3 jours |

### Qualité de code

| Amélioration | Statut | Effort |
|--------------|--------|--------|
| Tests unitaires repositories | Partiel | 5 jours |
| Tests d'intégration E2E | Absent | 8 jours |
| CI/CD pipeline | Absent | 2 jours |
| Code coverage >80% | ~40% | 10 jours |

### Sécurité

| Amélioration | Statut | Effort |
|--------------|--------|--------|
| Authentification 2FA | Absent | 3 jours |
| SSO (SAML/OIDC) | Absent | 5 jours |
| Audit de sécurité externe | Non fait | Variable |
| Penetration testing | Non fait | Variable |

---

## Roadmap suggérée

### Sprint 1 (2 semaines)
- [x] Audit fonctionnel
- [ ] Groupes de membres (P1)
- [ ] Début consolidation UX

### Sprint 2 (2 semaines)
- [ ] Fin consolidation UX opérateur (P1)
- [ ] Calendrier des séances (P2)

### Sprint 3 (2 semaines)
- [ ] Analytics avancés (P2)
- [ ] Templates emails (voir PLAN_INVITATIONS.md)

### Sprint 4 (2 semaines)
- [ ] Exports XLSX (voir PLAN_EXPORTS.md)
- [ ] Imports étendus

### Sprint 5+ (futur)
- [ ] WebSocket temps réel
- [ ] Multi-langue
- [ ] Séances récurrentes
- [ ] Champs personnalisés

---

## Tableau récapitulatif

| Fonctionnalité | Priorité | Effort | Dépendances |
|----------------|----------|--------|-------------|
| Consolidation UX | P1 | 5j | - |
| Groupes membres | P1 | 3j | - |
| Calendrier | P2 | 3j | - |
| Analytics | P2 | 5j | Chart.js |
| WebSocket | P2 | 8j | Ratchet/Swoole |
| Multi-langue | P3 | 5j+2j/lang | - |
| Récurrence | P3 | 5j | - |
| Champs custom | P3 | 6j | - |
| App mobile PWA | P4 | 15j | Service Worker |
| Mode hors-ligne | P4 | 12j | PWA |
| Tests E2E | - | 8j | Playwright |
| OpenAPI complet | - | 3j | - |
| 2FA | - | 3j | - |

**Total développement identifié** : ~80 jours

---

## Conclusion

AG-Vote est une application mature et fonctionnelle pour les assemblées générales et votes formels. Les améliorations identifiées sont des **enrichissements** et non des **corrections critiques**.

**Recommandations immédiates** (P1) :
1. Implémenter les groupes de membres (3 jours)
2. Consolider l'interface opérateur (5 jours)

**Recommandations à moyen terme** (P2) :
3. Ajouter le calendrier (3 jours)
4. Dashboard analytics (5 jours)

Ces améliorations apporteront une valeur significative aux utilisateurs sans compromettre la stabilité existante.
