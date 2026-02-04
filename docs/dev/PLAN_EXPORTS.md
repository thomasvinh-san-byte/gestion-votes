# Plan d'amélioration des exports

Ce document décrit les améliorations prévues pour le système d'export de données afin de rendre les données plus accessibles aux utilisateurs non-techniques et de permettre l'import de données préparées.

---

## État actuel

### Formats d'export disponibles

| Export | Format | Description | Déclencheur |
|--------|--------|-------------|-------------|
| Présences | CSV | Participants, mode, arrivée/départ, procurations | Post-validation |
| Votes | CSV | Résolution, votant, choix, poids, source, timestamp | Post-validation |
| Membres | CSV | Liste des membres avec pouvoir de vote | À tout moment |
| Résolutions | CSV | Titres, descriptions, statuts | À tout moment |
| Audit | CSV | Journal complet des événements | À tout moment |
| PV | HTML | Procès-verbal formaté | Post-validation |
| PV | PDF | Document imprimable (Dompdf) | Post-validation |

### Points forts actuels

- BOM UTF-8 pour ouverture directe dans Excel
- Séparateur `;` compatible Excel français
- Export verrouillé avant validation (conformité)
- Hash SHA256 pour intégrité PDF

### Limitations identifiées

1. **Format unique CSV** : Pas de support natif XLS/XLSX
2. **Import limité** : Seuls les membres sont importables
3. **Pas de templates** : Pas de modèles d'export personnalisables
4. **Colonnes fixes** : Impossible de choisir les colonnes à exporter
5. **Pas de rapports agrégés** : Pas de statistiques multi-séances

---

## Phase 1 — Support Excel natif (XLSX)

### Objectif

Permettre l'export en format XLSX natif pour une meilleure compatibilité avec Excel et les outils de tableur modernes.

### Solution technique

Utiliser la bibliothèque **PhpSpreadsheet** (successeur de PHPExcel) :

```bash
composer require phpoffice/phpspreadsheet
```

### Fichiers à créer/modifier

```
app/Service/ExportService.php          # Nouveau service d'export
app/Service/SpreadsheetExporter.php    # Wrapper PhpSpreadsheet
public/api/v1/export_attendance_xlsx.php
public/api/v1/export_votes_xlsx.php
public/api/v1/export_members_xlsx.php
public/api/v1/export_motions_xlsx.php
public/api/v1/export_audit_xlsx.php
public/api/v1/export_full_xlsx.php     # Export complet (multi-feuilles)
```

### Structure ExportService

```php
class ExportService {
    public function exportAttendance(string $meetingId, string $format): StreamedResponse;
    public function exportVotes(string $meetingId, string $format): StreamedResponse;
    public function exportMembers(string $tenantId, string $format): StreamedResponse;
    public function exportMotions(string $meetingId, string $format): StreamedResponse;
    public function exportAudit(string $meetingId, string $format): StreamedResponse;
    public function exportFullWorkbook(string $meetingId): StreamedResponse; // XLSX multi-feuilles
}
```

### Export complet (workbook multi-feuilles)

Un seul fichier XLSX contenant toutes les données de la séance :

| Feuille | Contenu |
|---------|---------|
| Résumé | KPIs, dates, décisions |
| Présences | Liste émargement |
| Résolutions | Motions avec résultats |
| Votes | Détail des bulletins |
| Procurations | Mandats |
| Audit | Journal chronologique |

### Livrables Phase 1

- [ ] Installation PhpSpreadsheet
- [ ] Service ExportService.php
- [ ] 6 endpoints d'export XLSX
- [ ] Export workbook complet
- [ ] Mise à jour UI (boutons d'export)
- [ ] Tests unitaires

### Effort estimé : 3 jours

---

## Phase 2 — Imports étendus

### Objectif

Permettre la préparation des données en amont via fichiers CSV/XLSX.

### Imports à implémenter

| Import | Données | Statut |
|--------|---------|--------|
| Membres | Nom, email, pouvoir | **Existant** |
| Résolutions | Titre, description, ordre | À créer |
| Présences | Membre, mode, procuration | À créer |
| Procurations | Mandant, mandataire, portée | À créer |

### Endpoint : motions_import_csv.php

```php
/**
 * Colonnes acceptées :
 * - title / titre : Titre de la résolution (requis)
 * - description : Texte complet
 * - order / ordre : Position dans l'ordre du jour
 * - category / categorie : Catégorie (optionnel)
 */
```

### Endpoint : attendances_import_csv.php

```php
/**
 * Colonnes acceptées :
 * - member_email / email : Email du membre (requis)
 * - mode : present, remote, proxy, excused
 * - proxy_to_email : Email du mandataire (si mode=proxy)
 * - checked_in_at : Date/heure d'arrivée
 */
```

### Endpoint : proxies_import_csv.php

```php
/**
 * Colonnes acceptées :
 * - grantor_email : Email du mandant (requis)
 * - grantee_email : Email du mandataire (requis)
 * - scope : full, specific (défaut: full)
 * - motion_ids : IDs des résolutions (si scope=specific)
 */
```

### Validation et sécurité

- Limite de taille : 5 MB
- Rate limiting : 10 imports/heure
- Transaction atomique (rollback si erreur)
- Rapport d'erreurs détaillé
- Dry-run mode pour validation avant import

### Livrables Phase 2

- [ ] Endpoint motions_import_csv.php
- [ ] Endpoint attendances_import_csv.php
- [ ] Endpoint proxies_import_csv.php
- [ ] Templates CSV téléchargeables
- [ ] Documentation utilisateur
- [ ] Tests d'intégration

### Effort estimé : 4 jours

---

## Phase 3 — Templates d'export personnalisables

### Objectif

Permettre aux utilisateurs de définir des modèles d'export avec colonnes sélectionnées.

### Fonctionnalités

1. **Sélection de colonnes** : Choisir les champs à inclure
2. **Ordre des colonnes** : Réorganiser via drag & drop
3. **Renommage** : Labels personnalisés
4. **Sauvegarde** : Templates réutilisables par tenant

### Schéma base de données

```sql
CREATE TABLE export_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    name VARCHAR(100) NOT NULL,
    export_type VARCHAR(50) NOT NULL, -- attendance, votes, members, motions
    columns JSONB NOT NULL, -- [{"field": "full_name", "label": "Nom", "order": 1}]
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, name, export_type)
);
```

### API

```
GET  /api/v1/export_templates.php?type=attendance
POST /api/v1/export_templates.php  {name, export_type, columns}
GET  /api/v1/export_attendance_csv.php?meeting_id=X&template_id=Y
```

### Livrables Phase 3

- [ ] Table export_templates
- [ ] CRUD templates
- [ ] Interface UI de configuration
- [ ] Application des templates aux exports
- [ ] Templates par défaut par type

### Effort estimé : 3 jours

---

## Phase 4 — Rapports agrégés multi-séances

### Objectif

Fournir des statistiques et rapports couvrant plusieurs séances.

### Rapports à implémenter

| Rapport | Description |
|---------|-------------|
| Participation annuelle | Taux de présence par membre sur N séances |
| Résolutions votées | Historique des décisions par catégorie |
| Pouvoir de vote | Évolution des tantièmes |
| Procurations | Statistiques de délégation |
| Quorum | Historique de quorum par séance |

### Endpoint : reports_aggregate.php

```php
/**
 * Paramètres :
 * - report_type : participation, decisions, voting_power, proxies, quorum
 * - from_date : Date de début
 * - to_date : Date de fin
 * - meeting_ids : Liste de séances (optionnel)
 * - format : csv, xlsx, json
 */
```

### Livrables Phase 4

- [ ] Endpoint reports_aggregate.php
- [ ] 5 types de rapports
- [ ] Interface UI de sélection
- [ ] Export multi-format
- [ ] Graphiques optionnels (via JSON pour frontend)

### Effort estimé : 4 jours

---

## Interface utilisateur

### Modifications UI requises

1. **Page operator.htmx.html**
   - Section "Exports" avec boutons par format (CSV / XLSX)
   - Dropdown pour templates personnalisés
   - Bouton "Export complet"

2. **Page archives.htmx.html**
   - Mêmes options d'export
   - Filtres de sélection multi-séances
   - Accès aux rapports agrégés

3. **Page members.htmx.html**
   - Boutons d'import/export
   - Téléchargement template CSV

4. **Nouvelle page reports.htmx.html** (optionnel)
   - Dashboard de rapports
   - Sélection de période
   - Visualisations

---

## Templates CSV téléchargeables

Fournir des fichiers modèles pour faciliter l'import :

| Fichier | Colonnes |
|---------|----------|
| template_members.csv | nom;prenom;email;ponderation;actif |
| template_motions.csv | titre;description;ordre;categorie |
| template_attendances.csv | email;mode;proxy_email;heure_arrivee |
| template_proxies.csv | mandant_email;mandataire_email;portee |

Ces templates seront disponibles via :
- `/public/assets/templates/` (fichiers statiques)
- Bouton "Télécharger modèle" dans l'interface

---

## Estimation globale

| Phase | Contenu | Effort |
|-------|---------|--------|
| 1 | Support XLSX natif | 3 jours |
| 2 | Imports étendus | 4 jours |
| 3 | Templates personnalisables | 3 jours |
| 4 | Rapports agrégés | 4 jours |
| **Total** | | **14 jours** |

---

## Critères de succès

- [ ] Export XLSX fonctionnel pour tous les types
- [ ] Import CSV pour motions, présences, procurations
- [ ] Templates d'export sauvegardables
- [ ] Au moins 3 rapports agrégés
- [ ] Documentation utilisateur complète
- [ ] Tests automatisés pour chaque endpoint
- [ ] Performances acceptables (<5s pour export 1000 lignes)

---

## Dépendances

| Bibliothèque | Version | Usage |
|--------------|---------|-------|
| phpoffice/phpspreadsheet | ^1.29 | Export XLSX |

Aucune autre dépendance externe requise.
