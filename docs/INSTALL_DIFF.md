# AG-VOTE - Mise à jour différentielle

## Contenu de ce ZIP

### Nouveaux endpoints API (12 fichiers)
```
public/api/v1/
├── trust_anomalies.php      # Détection anomalies séance
├── trust_checks.php         # Contrôles de cohérence
├── attendances_bulk.php     # Marquage présences en masse
├── speech_next.php          # Prochain orateur
├── audit_log.php            # Journal d'audit paginé
├── meeting_summary.php      # Résumé statistique séance
├── meeting_generate_report_pdf.php  # Export PV en PDF
├── proxies_delete.php       # Suppression procuration
├── attendance_export.php    # Alias export présences
├── votes_export.php         # Alias export votes
├── motions_export.php       # Alias export résolutions
└── members_export.php       # Alias export membres
```

### Pages HTML refactorisées (2 fichiers)
```
public/
├── paper_redeem.htmx.html   # Rachat bulletins papier (Design System)
└── invitations.htmx.html    # Envoi invitations (Design System)
```

### Middleware amélioré (1 fichier)
```
app/Core/Security/
└── AuthMiddleware.php       # RBAC avec permissions granulaires
```

### Scripts de test (2 fichiers)
```
scripts/
├── test_api_integration.php # Tests automatisés API
└── recette_demo.php         # Scénario recette complet
```

### Documentation (1 fichier)
```
docs/api/
└── openapi.yaml             # Spécification OpenAPI 3.0
```

## Installation

1. **Extraire** ce ZIP à la racine de votre projet :
   ```bash
   cd /chemin/vers/ag-vote
   unzip -o ag-vote-diff-update.zip
   ```

2. **Vérifier** que les fichiers sont en place :
   ```bash
   ls -la public/api/v1/trust_*.php
   ls -la scripts/*.php
   ```

3. **Tester** l'intégration :
   ```bash
   php scripts/test_api_integration.php --base-url=http://localhost:8080
   ```

## Dépendances

Pour `meeting_generate_report_pdf.php`, installer Dompdf :
```bash
composer require dompdf/dompdf
```

## Notes

- Ces fichiers sont **additifs** ou **remplacent** des versions existantes
- Aucune migration de base de données requise
- Compatible avec le Design System existant
