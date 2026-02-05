# Référence API — AG-Vote

## Conventions

Base URL : `/api/v1/`

### Authentification

- Header : `X-Api-Key: <clé>`
- Ou session PHP (après connexion via `auth_login.php`)

### Format de réponse

Succès :

```json
{"ok": true, "data": {...}}
```

Erreur :

```json
{"ok": false, "error": "code_erreur", "detail": "Message explicatif."}
```

### Codes HTTP

| Code | Signification |
|------|---------------|
| 200  | Succès |
| 400  | Requête invalide |
| 403  | Accès refusé (rôle insuffisant) |
| 404  | Ressource introuvable |
| 405  | Méthode non autorisée |
| 409  | Conflit (séance validée, motion déjà ouverte, etc.) |
| 422  | Paramètre manquant ou invalide |
| 429  | Rate limit atteint |
| 500  | Erreur serveur |

### Rôles

- Rôles système : `admin`, `operator`, `auditor`, `viewer`
- Rôles de séance : `president`, `assessor`, `voter`
- `public` = pas d'authentification requise

---

## Authentification

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `auth_login.php` | POST | public | Connexion par clé API. Crée une session PHP. Body : `{"api_key": "..."}` |
| `auth_logout.php` | POST | tout rôle | Déconnexion. Détruit la session. |
| `whoami.php` | GET | tout rôle | Retourne l'utilisateur courant (id, email, rôle). |
| `ping.php` | GET | public | Health check. Retourne `{"ok": true, "ts": "ISO8601"}`. |

---

## Séances (Meetings)

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `meetings.php` | GET | operator | Liste des séances du tenant. |
| `meetings.php` | POST | operator | Créer une séance. Body : `{"title": "...", "scheduled_at": "..."}` |
| `meetings_index.php` | GET | operator | Liste paginée des séances. |
| `meetings_update.php` | POST | operator | Modifier une séance (titre, statut, lieu). |
| `meetings_archive.php` | GET | operator | Liste des séances archivées. |
| `meeting_status.php` | GET | public | Statut de la séance en cours (live). |
| `meeting_status_for_meeting.php` | GET | auditor | Statut détaillé d'une séance. `?meeting_id=UUID` |
| `meeting_summary.php` | GET | operator | Résumé (présences, votes, proxies, incidents). |
| `meeting_stats.php` | GET | operator | Statistiques détaillées. |
| `meeting_transition.php` | POST | operator | Transition d'état (draft>scheduled>live, etc.). |
| `meeting_ready_check.php` | GET | auditor | Checklist de préparation. |
| `meeting_validate.php` | POST | president/admin | Valider et verrouiller la séance. |
| `meeting_consolidate.php` | POST | auditor | Consolider les résultats. |
| `meeting_reset_demo.php` | POST | admin | Reset demo (dev uniquement). |

---

## Résolutions (Motions)

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `motions.php` | POST | operator | Créer/modifier une résolution. |
| `motions_for_meeting.php` | GET | public | Liste des résolutions d'une séance. |
| `motions_open.php` | POST | operator | Ouvrir le vote sur une résolution. |
| `motions_close.php` | POST | operator | Clôturer le vote. |
| `motion_delete.php` | POST | operator | Supprimer une résolution (draft uniquement). |
| `motion_vote_override.php` | POST | operator/president | Surcharge manuelle du résultat. |
| `motion_quorum_override.php` | POST | operator/president | Surcharge manuelle du quorum. |
| `current_motion.php` | GET | public | Résolution actuellement ouverte au vote. |

---

## Présences (Attendances)

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `attendances.php` | GET | public | Liste des présences. `?meeting_id=UUID` |
| `attendances_bulk.php` | POST | operator | Pointage en masse. Body : `{"meeting_id": "...", "member_ids": [...], "mode": "present"}` |
| `attendances_upsert.php` | POST | operator | Créer/modifier une présence individuelle. |

---

## Procurations (Proxies)

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `proxies.php` | GET | public | Liste des procurations. `?meeting_id=UUID` |
| `proxies_upsert.php` | POST | operator | Créer/modifier une procuration. |
| `proxies_delete.php` | POST | operator | Révoquer une procuration. |

---

## Vote électronique

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `vote_tokens_generate.php` | POST | operator | Générer des tokens de vote pour une motion. |
| `ballots_cast.php` | POST | public | Voter. Body : `{"token": "...", "value": "for\|against\|abstain"}` |
| `ballots_result.php` | GET | public | Résultats en cours d'une motion. `?motion_id=UUID` |
| `manual_vote.php` | POST | operator | Vote manuel (mode dégradé). |
| `degraded_tally.php` | POST | operator/auditor | Saisie manuelle du décompte. |

---

## Vote papier

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `paper_ballot_issue.php` | POST | operator | Émettre un bulletin papier (code unique). |
| `paper_ballot_redeem.php` | POST | public | Utiliser un bulletin papier. |

---

## Membres

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `members.php` | GET/POST | operator | Lister/créer des membres. |
| `members_import_csv.php` | POST | operator | Import CSV de membres. |
| `members_export.php` | GET | operator | Export des membres. |
| `members_export_csv.php` | GET | operator | Export CSV des membres. |

---

## Invitations

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `invitations_create.php` | POST | operator | Créer une invitation (token). |
| `invitations_list.php` | GET | operator | Lister les invitations. |
| `invitations_send_bulk.php` | POST | operator | Envoyer les invitations par email. |
| `invitations_redeem.php` | POST | public | Utiliser une invitation. |

---

## Ordres du jour (Agendas)

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `agendas.php` | GET/POST | operator | Gérer les points de l'ordre du jour. |
| `agendas_for_meeting.php` | GET | public | Lister les points pour une séance. |

---

## Quorum et politiques

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `quorum_status.php` | GET | public | Statut du quorum. |
| `quorum_policies.php` | GET | public | Politiques de quorum disponibles. |
| `quorum_motions_list.php` | GET | operator | Motions avec statut quorum. |
| `quorum_card.php` | GET | operator | Carte résumée du quorum. |
| `vote_policies.php` | GET | public | Politiques de vote disponibles. |
| `meeting_quorum_settings.php` | GET/POST | operator | Configurer le quorum de la séance. |
| `meeting_vote_settings.php` | GET/POST | operator | Configurer la politique de vote. |
| `meeting_late_rules.php` | GET/POST | operator | Règles de vote tardif. |

---

## Exports et rapports

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `meeting_report.php` | GET | auditor | Récupérer le rapport HTML de la séance. |
| `meeting_generate_report.php` | POST | operator | Générer le rapport HTML. |
| `meeting_generate_report_pdf.php` | POST | operator/auditor | Générer le rapport PDF. |
| `export_pv_html.php` | GET | operator | Exporter le PV en HTML. |
| `export_attendance_csv.php` | GET | operator | Exporter les présences CSV. |
| `export_votes_csv.php` | GET | operator | Exporter les votes CSV. |
| `export_members_csv.php` | GET | operator | Exporter les membres CSV. |
| `export_motions_results_csv.php` | GET | operator/auditor | Exporter les résultats CSV. |
| `export_ballots_audit_csv.php` | GET | operator | Exporter l'audit des bulletins CSV. |
| `archives_list.php` | GET | public | Liste des séances archivées. |

---

## Audit et contrôle

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `audit_log.php` | GET | auditor/admin | Journal d'audit paginé. |
| `audit_export.php` | GET | auditor | Exporter le journal d'audit complet. |
| `meeting_audit.php` | GET | auditor | Audit d'une séance spécifique. |
| `meeting_audit_events.php` | GET | auditor | Événements d'audit détaillés. |
| `trust_overview.php` | GET | auditor | Vue d'ensemble contrôle et santé. |
| `trust_anomalies.php` | GET | auditor | Anomalies détectées. |
| `trust_checks.php` | GET | auditor | Vérifications de conformité. |
| `operator_anomalies.php` | GET | operator | Anomalies live pour l'opérateur. |
| `operator_audit_events.php` | GET | operator | Fil d'audit opérateur. |

---

## Administration

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `admin_users.php` | GET/POST | admin | CRUD utilisateurs système. |
| `admin_roles.php` | GET | admin | Lister les permissions par rôle. |
| `admin_meeting_roles.php` | GET/POST | admin/operator | Assigner des rôles de séance (president, assessor, voter). |
| `admin_system_status.php` | GET | admin | Santé système (DB, disque, échecs auth). |
| `admin_quorum_policies.php` | GET/POST | admin | CRUD politiques de quorum. |
| `admin_vote_policies.php` | GET/POST | admin | CRUD politiques de vote. |
| `admin_reset_demo.php` | POST | admin | Reset demo. |

---

## Notifications

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `notifications_list.php` | GET | operator | Liste des notifications paginée. |
| `notifications_recent.php` | GET | operator | Notifications récentes. |
| `notifications_mark_read.php` | POST | operator | Marquer une notification comme lue. |
| `notifications_mark_all_read.php` | POST | operator | Marquer toutes comme lues. |
| `notifications_clear.php` | POST | operator | Supprimer les notifications. |

---

## Prise de parole (Speech)

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `speech_queue.php` | GET | operator | File d'attente. |
| `speech_current.php` | GET | operator | Intervenant actuel. |
| `speech_request.php` | POST | operator | Demande de parole. |
| `speech_grant.php` | POST | operator | Accorder la parole. |
| `speech_end.php` | POST | operator | Fin de parole. |
| `speech_next.php` | POST | operator | Intervenant suivant. |
| `speech_clear.php` | POST | operator | Vider la file. |

---

## Appareils (Devices)

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `device_heartbeat.php` | POST | public | Signal de vie tablette. |
| `devices_list.php` | GET | operator | Liste des appareils connectés. |
| `device_block.php` | POST | operator | Bloquer un appareil. |
| `device_unblock.php` | POST | operator | Débloquer un appareil. |
| `device_kick.php` | POST | operator | Déconnecter un appareil. |

---

## Écran public

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `projector_state.php` | GET | public | État actuel pour l'écran de projection. |
| `dashboard.php` | GET | operator | Données du tableau de bord. |

---

## Urgences

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `emergency_panel.php` | GET | operator | Panneau d'urgence. |
| `emergency_procedures.php` | GET | operator | Procédures d'urgence disponibles. |
| `emergency_check_toggle.php` | POST | operator | Cocher un point de la checklist urgence. |
| `vote_incident.php` | POST | operator | Déclarer un incident de vote. |

---

## Développement

| Endpoint | Méthode | Rôle | Description |
|----------|---------|------|-------------|
| `dev_seed_members.php` | POST | admin | Injecter des membres de test. |
| `dev_seed_attendances.php` | POST | admin | Injecter des présences de test. |
