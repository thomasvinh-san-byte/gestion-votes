# Plan d'amélioration du système d'invitations

Ce document décrit les améliorations prévues pour le système d'envoi d'invitations par email aux participants des séances de vote.

---

## État actuel

### Fonctionnalités existantes

| Fonctionnalité | Statut | Description |
|----------------|--------|-------------|
| Génération de tokens | Existant | Token unique par membre/séance |
| Envoi SMTP | Existant | Service MailerService.php |
| Template HTML | Existant | Template unique (email_invitation.php) |
| Dry-run mode | Existant | Test sans envoi réel |
| Suivi de statut | Existant | pending, sent, bounced |
| Renvoi aux non-envoyés | Existant | Option only_unsent |

### Variables disponibles dans le template

```php
$meetingTitle  // Titre de la séance
$memberName    // Nom du participant
$voteUrl       // Lien de vote avec token
$appUrl        // URL de l'application
```

### Limitations identifiées

1. **Template unique** : Impossible de personnaliser le message
2. **Variables limitées** : Peu de données contextuelles
3. **Pas de rappels** : Envoi unique, pas de relances automatiques
4. **Pas de SMS** : Email uniquement
5. **Interface basique** : Pas de prévisualisation
6. **Pas de planification** : Envoi immédiat uniquement
7. **Suivi limité** : Pas de métriques d'ouverture

---

## Phase 1 — Templates personnalisables

### Objectif

Permettre la création de modèles d'email personnalisés avec variables enrichies.

### Nouvelles variables

| Variable | Description | Exemple |
|----------|-------------|---------|
| `{{member_name}}` | Nom complet | Jean Dupont |
| `{{member_first_name}}` | Prénom | Jean |
| `{{member_email}}` | Email | jean@example.com |
| `{{member_voting_power}}` | Pouvoir de vote | 150 |
| `{{meeting_title}}` | Titre séance | AG 2024 |
| `{{meeting_date}}` | Date formatée | 15 janvier 2024 |
| `{{meeting_time}}` | Heure | 14h30 |
| `{{meeting_location}}` | Lieu | Salle des fêtes |
| `{{meeting_status}}` | Statut | En cours |
| `{{vote_url}}` | Lien de vote | https://... |
| `{{app_url}}` | URL application | https://... |
| `{{token}}` | Token brut | abc123... |
| `{{motions_count}}` | Nombre résolutions | 5 |
| `{{tenant_name}}` | Nom organisation | Syndicat ABC |
| `{{current_date}}` | Date envoi | 10/01/2024 |

### Schéma base de données

```sql
CREATE TABLE email_templates (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    name VARCHAR(100) NOT NULL,
    template_type VARCHAR(50) NOT NULL, -- invitation, reminder, confirmation
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT, -- Version plain text (optionnel)
    is_default BOOLEAN DEFAULT false,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(tenant_id, name)
);

-- Template par défaut
INSERT INTO email_templates (tenant_id, name, template_type, subject, body_html, is_default)
VALUES (
    '00000000-0000-0000-0000-000000000001',
    'Invitation standard',
    'invitation',
    'Invitation de vote – {{meeting_title}}',
    '<html>...</html>',
    true
);
```

### API Templates

```
GET  /api/v1/email_templates.php           # Liste templates
GET  /api/v1/email_templates.php?id=X      # Détail template
POST /api/v1/email_templates.php           # Créer template
PUT  /api/v1/email_templates.php?id=X      # Modifier template
DELETE /api/v1/email_templates.php?id=X    # Supprimer template
POST /api/v1/email_templates_preview.php   # Prévisualisation avec données test
```

### Service de rendu

```php
class EmailTemplateService {
    public function render(string $templateId, array $variables): string;
    public function getVariables(string $meetingId, string $memberId): array;
    public function preview(string $templateBody, array $sampleData): string;
    public function validate(string $templateBody): array; // Liste variables inconnues
}
```

### Interface UI

Ajouter une page `/settings/email-templates.htmx.html` avec :

- Liste des templates existants
- Éditeur WYSIWYG simplifié (ou textarea avec aide)
- Insertion de variables via boutons
- Prévisualisation live
- Copier template par défaut

### Livrables Phase 1

- [ ] Table email_templates
- [ ] CRUD API templates
- [ ] Service EmailTemplateService.php
- [ ] Interface de gestion templates
- [ ] Prévisualisation
- [ ] Migration template existant

### Effort estimé : 4 jours

---

## Phase 2 — Envoi programmé et rappels

### Objectif

Permettre la planification des envois et les rappels automatiques.

### Fonctionnalités

1. **Envoi programmé** : Définir date/heure d'envoi
2. **Rappels automatiques** : J-7, J-3, J-1 avant la séance
3. **File d'attente** : Gestion asynchrone des envois
4. **Retry automatique** : Relance en cas d'échec SMTP

### Schéma base de données

```sql
CREATE TABLE email_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL,
    meeting_id UUID REFERENCES meetings(id),
    member_id UUID REFERENCES members(id),
    template_id UUID REFERENCES email_templates(id),
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    scheduled_at TIMESTAMPTZ NOT NULL,
    sent_at TIMESTAMPTZ,
    status VARCHAR(20) DEFAULT 'pending', -- pending, sent, failed, cancelled
    retry_count INT DEFAULT 0,
    last_error TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_email_queue_scheduled ON email_queue(scheduled_at) WHERE status = 'pending';
CREATE INDEX idx_email_queue_meeting ON email_queue(meeting_id);

CREATE TABLE reminder_schedules (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    meeting_id UUID NOT NULL REFERENCES meetings(id),
    template_id UUID REFERENCES email_templates(id),
    days_before INT NOT NULL, -- 7, 3, 1, 0 (jour J)
    send_time TIME DEFAULT '09:00',
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(meeting_id, days_before)
);
```

### Worker de traitement

```php
// scripts/process_email_queue.php
// Exécuté via cron toutes les 5 minutes

class EmailQueueWorker {
    public function processQueue(int $batchSize = 50): array;
    public function scheduleReminders(string $meetingId): void;
    public function cancelMeetingEmails(string $meetingId): void;
}
```

### Cron configuration

```bash
# /etc/cron.d/agvote-emails
*/5 * * * * www-data php /var/www/agvote/scripts/process_email_queue.php
```

### API

```
POST /api/v1/invitations_schedule.php
    {meeting_id, template_id, scheduled_at}

POST /api/v1/reminders_configure.php
    {meeting_id, reminders: [{days_before: 7, template_id: X}, ...]}

GET  /api/v1/email_queue.php?meeting_id=X  # Voir file d'attente
POST /api/v1/email_queue_cancel.php        # Annuler envois programmés
```

### Livrables Phase 2

- [ ] Table email_queue
- [ ] Table reminder_schedules
- [ ] Worker de traitement
- [ ] Configuration cron
- [ ] API planification
- [ ] Interface UI programmation
- [ ] Visualisation file d'attente

### Effort estimé : 5 jours

---

## Phase 3 — Métriques et suivi

### Objectif

Suivre la délivrabilité et l'engagement des emails.

### Métriques à collecter

| Métrique | Description | Méthode |
|----------|-------------|---------|
| Envoyés | Nombre d'emails envoyés | Comptage base |
| Délivrés | Confirmés par SMTP | Réponse serveur |
| Rebonds | Échecs permanents | Code erreur SMTP |
| Ouverts | Emails lus | Pixel tracking |
| Cliqués | Liens suivis | Redirect tracking |

### Schéma base de données

```sql
ALTER TABLE invitations ADD COLUMN opened_at TIMESTAMPTZ;
ALTER TABLE invitations ADD COLUMN clicked_at TIMESTAMPTZ;
ALTER TABLE invitations ADD COLUMN open_count INT DEFAULT 0;

CREATE TABLE email_events (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    invitation_id UUID REFERENCES invitations(id),
    event_type VARCHAR(20) NOT NULL, -- sent, delivered, bounced, opened, clicked
    event_data JSONB,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_email_events_invitation ON email_events(invitation_id);
```

### Endpoints de tracking

```
GET /api/v1/email_pixel.php?id=X     # Pixel 1x1 transparent (tracking ouverture)
GET /api/v1/email_redirect.php?id=X&url=Y  # Redirection tracking (clics)
```

### Dashboard métriques

Ajouter dans l'interface opérateur :

- Taux d'envoi : X/Y envoyés
- Taux de rebond : X%
- Taux d'ouverture : X%
- Taux de clic : X%
- Liste des non-envoyés avec action de renvoi

### Livrables Phase 3

- [ ] Colonnes tracking invitations
- [ ] Table email_events
- [ ] Endpoint pixel tracking
- [ ] Endpoint redirect tracking
- [ ] Injection pixel dans templates
- [ ] Dashboard métriques
- [ ] Export statistiques

### Effort estimé : 3 jours

### Note sur la vie privée

Le tracking d'ouverture peut être désactivé via configuration :
```
EMAIL_TRACKING_ENABLED=0
```

---

## Phase 4 — Canal SMS (optionnel)

### Objectif

Ajouter l'envoi de SMS comme canal complémentaire.

### Prérequis

- Compte chez un fournisseur SMS (Twilio, OVH SMS, etc.)
- Numéros de téléphone des membres

### Schéma base de données

```sql
ALTER TABLE members ADD COLUMN phone VARCHAR(20);
ALTER TABLE members ADD COLUMN phone_verified BOOLEAN DEFAULT false;

CREATE TABLE sms_queue (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id UUID NOT NULL,
    meeting_id UUID REFERENCES meetings(id),
    member_id UUID REFERENCES members(id),
    phone VARCHAR(20) NOT NULL,
    message TEXT NOT NULL,
    scheduled_at TIMESTAMPTZ NOT NULL,
    sent_at TIMESTAMPTZ,
    status VARCHAR(20) DEFAULT 'pending',
    provider_id VARCHAR(100), -- ID retourné par le fournisseur
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

### Service SMS

```php
interface SmsProviderInterface {
    public function send(string $phone, string $message): array;
    public function getStatus(string $providerId): string;
}

class TwilioProvider implements SmsProviderInterface { }
class OvhSmsProvider implements SmsProviderInterface { }

class SmsService {
    public function __construct(SmsProviderInterface $provider);
    public function sendInvitation(string $meetingId, string $memberId): array;
    public function sendReminder(string $meetingId, string $memberId): array;
}
```

### Template SMS

Message court (~160 caractères) :
```
Votre lien de vote pour {{meeting_title}}: {{vote_url}}
Ne partagez pas ce lien.
```

### Livrables Phase 4

- [ ] Colonne phone sur members
- [ ] Table sms_queue
- [ ] Interface SmsProviderInterface
- [ ] Implémentation Twilio ou OVH
- [ ] Worker SMS
- [ ] UI envoi SMS
- [ ] Import CSV avec téléphones

### Effort estimé : 4 jours

---

## Interface utilisateur améliorée

### Modifications requises

1. **Page operator.htmx.html**
   - Section "Invitations" améliorée
   - Sélection de template
   - Prévisualisation avant envoi
   - Programmation date/heure
   - Indicateurs de statut (envoyés/en attente/échecs)

2. **Nouvelle section ou page pour templates**
   - Liste des templates
   - Éditeur avec variables
   - Prévisualisation
   - Templates par type (invitation, rappel, confirmation)

3. **Dashboard métriques (dans operator)**
   - Statistiques d'envoi
   - Graphique de délivrabilité
   - Actions rapides (renvoyer aux échecs)

### Wireframe conceptuel

```
┌─────────────────────────────────────────────────────────┐
│ Invitations                                    [Envoyer]│
├─────────────────────────────────────────────────────────┤
│ Template: [Invitation standard      ▼] [Prévisualiser] │
│ Destinataires: ○ Tous  ○ Non envoyés  ○ Sélection     │
│ Programmation: [_] Envoyer maintenant                  │
│                [_] Programmer pour: [____/____/____]   │
├─────────────────────────────────────────────────────────┤
│ Statistiques                                           │
│ ┌────────┐ ┌────────┐ ┌────────┐ ┌────────┐          │
│ │   45   │ │   42   │ │    2   │ │   38   │          │
│ │ Envoyés│ │Délivrés│ │ Rebonds│ │ Ouverts│          │
│ └────────┘ └────────┘ └────────┘ └────────┘          │
├─────────────────────────────────────────────────────────┤
│ Membres avec échec d'envoi:                            │
│ • jean.dupont@email.com - Adresse invalide [Renvoyer] │
│ • marie.martin@email.com - Timeout [Renvoyer]         │
└─────────────────────────────────────────────────────────┘
```

---

## Estimation globale

| Phase | Contenu | Effort |
|-------|---------|--------|
| 1 | Templates personnalisables | 4 jours |
| 2 | Envoi programmé et rappels | 5 jours |
| 3 | Métriques et suivi | 3 jours |
| 4 | Canal SMS (optionnel) | 4 jours |
| **Total (sans SMS)** | | **12 jours** |
| **Total (avec SMS)** | | **16 jours** |

---

## Critères de succès

- [ ] Au moins 3 templates personnalisables
- [ ] 10+ variables disponibles dans templates
- [ ] Prévisualisation fonctionnelle
- [ ] Programmation d'envoi différé
- [ ] Rappels automatiques configurables
- [ ] Dashboard métriques de base
- [ ] Taux de délivrabilité >95%
- [ ] Documentation utilisateur

---

## Configuration SMTP recommandée

Pour production, utiliser un service de relay professionnel :

| Service | Avantages | Coût approximatif |
|---------|-----------|-------------------|
| Amazon SES | Haute délivrabilité, pas cher | ~0.10€/1000 emails |
| Sendgrid | Interface complète, webhooks | Gratuit jusqu'à 100/jour |
| Mailjet | Français, RGPD | Gratuit jusqu'à 200/jour |
| Postmark | Excellent pour transactionnel | ~1€/1000 emails |

Variables d'environnement :

```env
SMTP_HOST=smtp.sendgrid.net
SMTP_PORT=587
SMTP_USER=apikey
SMTP_PASS=SG.xxxxxxxxxxxxx
SMTP_FROM_EMAIL=votes@monorganisation.fr
SMTP_FROM_NAME=Organisation XYZ
SMTP_ENCRYPTION=tls
EMAIL_TRACKING_ENABLED=1
```

---

## Sécurité et conformité

### Bonnes pratiques

1. **Tokens uniques** : Déjà implémenté (32 caractères hex)
2. **Expiration** : Ajouter expiration des tokens (optionnel)
3. **Lien de désabonnement** : Requis par RGPD
4. **SPF/DKIM/DMARC** : Configurer DNS pour délivrabilité

### RGPD

- Les emails contiennent des données personnelles
- Consentement implicite (participation à l'assemblée)
- Droit de rectification via gestion membres
- Tracking désactivable
