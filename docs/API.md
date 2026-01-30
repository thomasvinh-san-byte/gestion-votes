# Reference API — AG-Vote

## Conventions

Base URL : `/api/v1/`

### Authentification

- Header : `X-Api-Key: <cle>`
- Ou session PHP (apres connexion via `auth_login.php`)

### Format de reponse

Succes :

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
| 200  | Succes |
| 400  | Requete invalide |
| 403  | Acces refuse (role insuffisant) |
| 404  | Ressource introuvable |
| 405  | Methode non autorisee |
| 409  | Conflit (seance validee, motion deja ouverte, etc.) |
| 422  | Parametre manquant ou invalide |
| 429  | Rate limit atteint |
| 500  | Erreur serveur |

### Roles

- Roles systeme : `admin`, `operator`, `auditor`, `viewer`
- Roles de seance : `president`, `assessor`, `voter`
- `public` = pas d'authentification requise

---

## Authentification

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `auth_login.php` | POST | public | Connexion par cle API. Cree une session PHP. Body : `{"api_key": "..."}` |
| `auth_logout.php` | POST | tout role | Deconnexion. Detruit la session. |
| `whoami.php` | GET | tout role | Retourne l'utilisateur courant (id, email, role). |
| `ping.php` | GET | public | Health check. Retourne `{"ok": true, "ts": "ISO8601"}`. |

---

## Seances (Meetings)

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `meetings.php` | GET | operator | Liste des seances du tenant. |
| `meetings.php` | POST | operator | Creer une seance. Body : `{"title": "...", "scheduled_at": "..."}` |
| `meetings_index.php` | GET | operator | Liste paginee des seances. |
| `meetings_update.php` | POST | operator | Modifier une seance (titre, statut, lieu). |
| `meetings_archive.php` | GET | operator | Liste des seances archivees. |
| `meeting_status.php` | GET | public | Statut de la seance en cours (live). |
| `meeting_status_for_meeting.php` | GET | auditor | Statut detaille d'une seance. `?meeting_id=UUID` |
| `meeting_summary.php` | GET | operator | Resume (presences, votes, proxies, incidents). |
| `meeting_stats.php` | GET | operator | Statistiques detaillees. |
| `meeting_transition.php` | POST | operator | Transition d'etat (draft>scheduled>live, etc.). |
| `meeting_ready_check.php` | GET | auditor | Checklist de preparation. |
| `meeting_validate.php` | POST | president/admin | Valider et verrouiller la seance. |
| `meeting_consolidate.php` | POST | auditor | Consolider les resultats. |
| `meeting_reset_demo.php` | POST | admin | Reset demo (dev uniquement). |

---

## Resolutions (Motions)

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `motions.php` | POST | operator | Creer/modifier une resolution. |
| `motions_for_meeting.php` | GET | public | Liste des resolutions d'une seance. |
| `motions_open.php` | POST | operator | Ouvrir le vote sur une resolution. |
| `motions_close.php` | POST | operator | Cloturer le vote. |
| `motion_delete.php` | POST | operator | Supprimer une resolution (draft uniquement). |
| `motion_vote_override.php` | POST | operator/president | Surcharge manuelle du resultat. |
| `motion_quorum_override.php` | POST | operator/president | Surcharge manuelle du quorum. |
| `current_motion.php` | GET | public | Resolution actuellement ouverte au vote. |

---

## Presences (Attendances)

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `attendances.php` | GET | public | Liste des presences. `?meeting_id=UUID` |
| `attendances_bulk.php` | POST | operator | Pointage en masse. Body : `{"meeting_id": "...", "member_ids": [...], "mode": "present"}` |
| `attendances_upsert.php` | POST | operator | Creer/modifier une presence individuelle. |

---

## Procurations (Proxies)

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `proxies.php` | GET | public | Liste des procurations. `?meeting_id=UUID` |
| `proxies_upsert.php` | POST | operator | Creer/modifier une procuration. |
| `proxies_delete.php` | POST | operator | Revoquer une procuration. |

---

## Vote electronique

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `vote_tokens_generate.php` | POST | operator | Generer des tokens de vote pour une motion. |
| `ballots_cast.php` | POST | public | Voter. Body : `{"token": "...", "value": "for\|against\|abstain"}` |
| `ballots_result.php` | GET | public | Resultats en cours d'une motion. `?motion_id=UUID` |
| `manual_vote.php` | POST | operator | Vote manuel (mode degrade). |
| `degraded_tally.php` | POST | operator/auditor | Saisie manuelle du decompte. |

---

## Vote papier

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `paper_ballot_issue.php` | POST | operator | Emettre un bulletin papier (code unique). |
| `paper_ballot_redeem.php` | POST | public | Utiliser un bulletin papier. |

---

## Membres

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `members.php` | GET/POST | operator | Lister/creer des membres. |
| `members_import_csv.php` | POST | operator | Import CSV de membres. |
| `members_export.php` | GET | operator | Export des membres. |
| `members_export_csv.php` | GET | operator | Export CSV des membres. |

---

## Invitations

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `invitations_create.php` | POST | operator | Creer une invitation (token). |
| `invitations_list.php` | GET | operator | Lister les invitations. |
| `invitations_send_bulk.php` | POST | operator | Envoyer les invitations par email. |
| `invitations_redeem.php` | POST | public | Utiliser une invitation. |

---

## Ordres du jour (Agendas)

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `agendas.php` | GET/POST | operator | Gerer les points de l'ordre du jour. |
| `agendas_for_meeting.php` | GET | public | Lister les points pour une seance. |

---

## Quorum & Politiques

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `quorum_status.php` | GET | public | Statut du quorum. |
| `quorum_policies.php` | GET | public | Politiques de quorum disponibles. |
| `quorum_motions_list.php` | GET | operator | Motions avec statut quorum. |
| `quorum_card.php` | GET | operator | Carte resumee du quorum. |
| `vote_policies.php` | GET | public | Politiques de vote disponibles. |
| `meeting_quorum_settings.php` | GET/POST | operator | Configurer le quorum de la seance. |
| `meeting_vote_settings.php` | GET/POST | operator | Configurer la politique de vote. |
| `meeting_late_rules.php` | GET/POST | operator | Regles de vote tardif. |

---

## Exports & Rapports

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `meeting_report.php` | GET | auditor | Recuperer le rapport HTML de la seance. |
| `meeting_generate_report.php` | POST | operator | Generer le rapport HTML. |
| `meeting_generate_report_pdf.php` | POST | operator/auditor | Generer le rapport PDF. |
| `export_pv_html.php` | GET | operator | Exporter le PV en HTML. |
| `export_attendance_csv.php` | GET | operator | Exporter les presences CSV. |
| `export_votes_csv.php` | GET | operator | Exporter les votes CSV. |
| `export_members_csv.php` | GET | operator | Exporter les membres CSV. |
| `export_motions_results_csv.php` | GET | operator/auditor | Exporter les resultats CSV. |
| `export_ballots_audit_csv.php` | GET | operator | Exporter l'audit des bulletins CSV. |
| `archives_list.php` | GET | public | Liste des seances archivees. |

---

## Audit & Controle

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `audit_log.php` | GET | auditor/admin | Journal d'audit pagine. |
| `audit_export.php` | GET | auditor | Exporter le journal d'audit complet. |
| `meeting_audit.php` | GET | auditor | Audit d'une seance specifique. |
| `meeting_audit_events.php` | GET | auditor | Evenements d'audit detailles. |
| `trust_overview.php` | GET | auditor | Vue d'ensemble controle & sante. |
| `trust_anomalies.php` | GET | auditor | Anomalies detectees. |
| `trust_checks.php` | GET | auditor | Verifications de conformite. |
| `operator_anomalies.php` | GET | operator | Anomalies live pour l'operateur. |
| `operator_audit_events.php` | GET | operator | Fil d'audit operateur. |

---

## Administration

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `admin_users.php` | GET/POST | admin | CRUD utilisateurs systeme. |
| `admin_roles.php` | GET | admin | Lister les permissions par role. |
| `admin_meeting_roles.php` | GET/POST | admin/operator | Assigner des roles de seance (president, assessor, voter). |
| `admin_system_status.php` | GET | admin | Sante systeme (DB, disque, echecs auth). |
| `admin_quorum_policies.php` | GET/POST | admin | CRUD politiques de quorum. |
| `admin_vote_policies.php` | GET/POST | admin | CRUD politiques de vote. |
| `admin_reset_demo.php` | POST | admin | Reset demo. |

---

## Notifications

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `notifications_list.php` | GET | operator | Liste des notifications paginee. |
| `notifications_recent.php` | GET | operator | Notifications recentes. |
| `notifications_mark_read.php` | POST | operator | Marquer une notification comme lue. |
| `notifications_mark_all_read.php` | POST | operator | Marquer toutes comme lues. |
| `notifications_clear.php` | POST | operator | Supprimer les notifications. |

---

## Prise de parole (Speech)

| Endpoint | Methode | Role | Description |
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

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `device_heartbeat.php` | POST | public | Signal de vie tablette. |
| `devices_list.php` | GET | operator | Liste des appareils connectes. |
| `device_block.php` | POST | operator | Bloquer un appareil. |
| `device_unblock.php` | POST | operator | Debloquer un appareil. |
| `device_kick.php` | POST | operator | Deconnecter un appareil. |

---

## Ecran public

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `projector_state.php` | GET | public | Etat actuel pour l'ecran de projection. |
| `dashboard.php` | GET | operator | Donnees du tableau de bord. |

---

## Urgences

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `emergency_panel.php` | GET | operator | Panneau d'urgence. |
| `emergency_procedures.php` | GET | operator | Procedures d'urgence disponibles. |
| `emergency_check_toggle.php` | POST | operator | Cocher un point de la checklist urgence. |
| `vote_incident.php` | POST | operator | Declarer un incident de vote. |

---

## Developpement

| Endpoint | Methode | Role | Description |
|----------|---------|------|-------------|
| `dev_seed_members.php` | POST | admin | Injecter des membres de test. |
| `dev_seed_attendances.php` | POST | admin | Injecter des presences de test. |
