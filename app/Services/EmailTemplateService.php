<?php
declare(strict_types=1);

namespace AgVote\Service;

use AgVote\Repository\EmailTemplateRepository;
use AgVote\Repository\MeetingRepository;
use AgVote\Repository\MemberRepository;

/**
 * Service for email template management and variable rendering.
 */
final class EmailTemplateService
{
    private EmailTemplateRepository $templateRepo;
    private MeetingRepository $meetingRepo;
    private MemberRepository $memberRepo;
    private string $appUrl;

    /**
     * List of available variables with descriptions.
     */
    public const AVAILABLE_VARIABLES = [
        '{{member_name}}' => 'Nom complet du membre',
        '{{member_first_name}}' => 'Prenom du membre',
        '{{member_email}}' => 'Email du membre',
        '{{member_voting_power}}' => 'Pouvoir de vote',
        '{{meeting_title}}' => 'Titre de la seance',
        '{{meeting_date}}' => 'Date formatee (15 janvier 2024)',
        '{{meeting_time}}' => 'Heure (14h30)',
        '{{meeting_location}}' => 'Lieu de la seance',
        '{{meeting_status}}' => 'Statut de la seance',
        '{{vote_url}}' => 'Lien de vote complet',
        '{{app_url}}' => 'URL de l\'application',
        '{{token}}' => 'Token d\'authentification',
        '{{motions_count}}' => 'Nombre de resolutions',
        '{{tenant_name}}' => 'Nom de l\'organisation',
        '{{current_date}}' => 'Date d\'envoi',
        '{{current_time}}' => 'Heure d\'envoi',
    ];

    /**
     * Default HTML template for invitations.
     */
    public const DEFAULT_INVITATION_TEMPLATE = <<<'HTML'
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{meeting_title}} - Invitation de vote</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
    Votre lien de vote pour: {{meeting_title}}
  </div>

  <div style="max-width:640px; margin:0 auto; padding:24px;">
    <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:22px;">
      <div style="font:700 18px/1.2 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Invitation de vote
      </div>
      <div style="margin-top:6px; color:#6b7280; font:14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Seance : <strong>{{meeting_title}}</strong>
      </div>

      <div style="margin-top:14px; font:14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Bonjour <strong>{{member_name}}</strong>,
      </div>

      <div style="margin-top:10px; color:#111827; font:14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Vous etes invite(e) a participer au vote pour la seance <strong>{{meeting_title}}</strong>
        prevue le <strong>{{meeting_date}}</strong> a <strong>{{meeting_time}}</strong>.
      </div>

      <div style="margin-top:18px;">
        <a href="{{vote_url}}"
           style="display:inline-block; background:#2563eb; color:#ffffff; text-decoration:none;
                  padding:10px 16px; border-radius:10px; font:600 14px/1 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
          Acceder au vote
        </a>
      </div>

      <div style="margin-top:14px; color:#6b7280; font:12px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Si le bouton ne fonctionne pas, copiez-collez ce lien dans votre navigateur :<br>
        <span style="word-break:break-all;">{{vote_url}}</span>
      </div>

      <hr style="border:none; border-top:1px solid #e5e7eb; margin:18px 0;">

      <div style="color:#6b7280; font:12px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Ce lien est personnel et strictement confidentiel. Ne le partagez pas.
      </div>
    </div>

    <div style="margin-top:12px; color:#9ca3af; font:12px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial; text-align:center;">
      Envoye par {{tenant_name}} - {{app_url}}
    </div>
  </div>
</body>
</html>
HTML;

    /**
     * Default HTML template for reminders.
     */
    public const DEFAULT_REMINDER_TEMPLATE = <<<'HTML'
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rappel: {{meeting_title}}</title>
</head>
<body style="margin:0; padding:0; background:#f3f4f6;">
  <div style="display:none; max-height:0; overflow:hidden; opacity:0; color:transparent;">
    Rappel: N'oubliez pas de voter pour {{meeting_title}}
  </div>

  <div style="max-width:640px; margin:0 auto; padding:24px;">
    <div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:16px; padding:22px;">
      <div style="font:700 18px/1.2 system-ui, -apple-system, Segoe UI, Roboto, Arial; color:#dc2626;">
        Rappel de vote
      </div>
      <div style="margin-top:6px; color:#6b7280; font:14px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Seance : <strong>{{meeting_title}}</strong>
      </div>

      <div style="margin-top:14px; font:14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Bonjour <strong>{{member_name}}</strong>,
      </div>

      <div style="margin-top:10px; color:#111827; font:14px/1.6 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Ce message est un rappel pour la seance de vote <strong>{{meeting_title}}</strong>
        qui aura lieu le <strong>{{meeting_date}}</strong> a <strong>{{meeting_time}}</strong>.
      </div>

      <div style="margin-top:18px;">
        <a href="{{vote_url}}"
           style="display:inline-block; background:#dc2626; color:#ffffff; text-decoration:none;
                  padding:10px 16px; border-radius:10px; font:600 14px/1 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
          Acceder au vote maintenant
        </a>
      </div>

      <hr style="border:none; border-top:1px solid #e5e7eb; margin:18px 0;">

      <div style="color:#6b7280; font:12px/1.5 system-ui, -apple-system, Segoe UI, Roboto, Arial;">
        Ce lien est personnel. Ne le partagez pas.
      </div>
    </div>
  </div>
</body>
</html>
HTML;

    public function __construct(array $config = [])
    {
        $this->templateRepo = new EmailTemplateRepository();
        $this->meetingRepo = new MeetingRepository();
        $this->memberRepo = new MemberRepository();
        $this->appUrl = (string)(($config['app']['url'] ?? '') ?: 'http://localhost:8080');
    }

    /**
     * Collects all variables for a member/meeting.
     */
    public function getVariables(
        string $tenantId,
        string $meetingId,
        string $memberId,
        string $token,
        ?string $tenantName = null
    ): array {
        $meeting = $this->meetingRepo->findByIdForTenant($meetingId, $tenantId);
        $member = $this->memberRepo->findByIdForTenant($memberId, $tenantId);

        $meetingDate = '';
        $meetingTime = '';
        if (!empty($meeting['scheduled_at'])) {
            $dt = new \DateTime($meeting['scheduled_at']);
            $formatter = new \IntlDateFormatter(
                'fr_FR',
                \IntlDateFormatter::LONG,
                \IntlDateFormatter::NONE
            );
            $meetingDate = $formatter->format($dt);
            $meetingTime = $dt->format('H\hi');
        }

        $voteUrl = rtrim($this->appUrl, '/') . "/vote.htmx.html?token=" . rawurlencode($token);

        // Extract first name (first word of full name)
        $fullName = (string)($member['full_name'] ?? '');
        $nameParts = explode(' ', trim($fullName));
        $firstName = $nameParts[0] ?? '';

        // Motion count
        $motionsCount = (int)($this->meetingRepo->countMotions($meetingId) ?? 0);

        return [
            '{{member_name}}' => $fullName,
            '{{member_first_name}}' => $firstName,
            '{{member_email}}' => (string)($member['email'] ?? ''),
            '{{member_voting_power}}' => (string)($member['voting_power'] ?? '1'),
            '{{meeting_title}}' => (string)($meeting['title'] ?? ''),
            '{{meeting_date}}' => $meetingDate,
            '{{meeting_time}}' => $meetingTime,
            '{{meeting_location}}' => (string)($meeting['location'] ?? ''),
            '{{meeting_status}}' => $this->translateStatus((string)($meeting['status'] ?? '')),
            '{{vote_url}}' => $voteUrl,
            '{{app_url}}' => $this->appUrl,
            '{{token}}' => $token,
            '{{motions_count}}' => (string)$motionsCount,
            '{{tenant_name}}' => $tenantName ?? 'Gestion Votes',
            '{{current_date}}' => (new \DateTime())->format('d/m/Y'),
            '{{current_time}}' => (new \DateTime())->format('H:i'),
        ];
    }

    /**
     * Renders a template with provided variables.
     */
    public function render(string $templateBody, array $variables): string
    {
        return str_replace(
            array_keys($variables),
            array_values($variables),
            $templateBody
        );
    }

    /**
     * Renders a complete template (subject + body).
     */
    public function renderTemplate(
        string $tenantId,
        string $templateId,
        string $meetingId,
        string $memberId,
        string $token,
        ?string $tenantName = null
    ): array {
        $template = $this->templateRepo->findById($templateId, $tenantId);
        if (!$template) {
            return ['ok' => false, 'error' => 'template_not_found'];
        }

        $variables = $this->getVariables($tenantId, $meetingId, $memberId, $token, $tenantName);

        return [
            'ok' => true,
            'subject' => $this->render($template['subject'], $variables),
            'body_html' => $this->render($template['body_html'], $variables),
            'body_text' => $template['body_text'] ? $this->render($template['body_text'], $variables) : null,
            'variables' => $variables,
        ];
    }

    /**
     * Previews a template with test data.
     */
    public function preview(string $templateBody, ?array $customVariables = null): string
    {
        $sampleData = $customVariables ?? [
            '{{member_name}}' => 'Jean Dupont',
            '{{member_first_name}}' => 'Jean',
            '{{member_email}}' => 'jean.dupont@example.com',
            '{{member_voting_power}}' => '150',
            '{{meeting_title}}' => 'Assemblee Generale 2024',
            '{{meeting_date}}' => '15 janvier 2024',
            '{{meeting_time}}' => '14h30',
            '{{meeting_location}}' => 'Salle des fetes',
            '{{meeting_status}}' => 'Programme',
            '{{vote_url}}' => 'https://votes.example.com/vote.htmx.html?token=abc123def456',
            '{{app_url}}' => 'https://votes.example.com',
            '{{token}}' => 'abc123def456',
            '{{motions_count}}' => '5',
            '{{tenant_name}}' => 'Organisation XYZ',
            '{{current_date}}' => date('d/m/Y'),
            '{{current_time}}' => date('H:i'),
        ];

        return $this->render($templateBody, $sampleData);
    }

    /**
     * Validates a template and returns unknown variables.
     */
    public function validate(string $templateBody): array
    {
        preg_match_all('/\{\{([a-z_]+)\}\}/', $templateBody, $matches);
        $usedVars = array_unique($matches[0] ?? []);
        $knownVars = array_keys(self::AVAILABLE_VARIABLES);

        $unknown = array_diff($usedVars, $knownVars);

        return [
            'valid' => empty($unknown),
            'used_variables' => $usedVars,
            'unknown_variables' => array_values($unknown),
        ];
    }

    /**
     * Lists available variables.
     */
    public function listAvailableVariables(): array
    {
        return self::AVAILABLE_VARIABLES;
    }

    /**
     * Creates default templates for a tenant.
     */
    public function createDefaultTemplates(string $tenantId, ?string $createdBy = null): array
    {
        $created = [];

        // Default invitation template
        $inv = $this->templateRepo->create(
            $tenantId,
            'Invitation standard',
            'invitation',
            'Invitation de vote - {{meeting_title}}',
            self::DEFAULT_INVITATION_TEMPLATE,
            null,
            true,
            $createdBy
        );
        if ($inv) $created[] = $inv;

        // Default reminder template
        $rem = $this->templateRepo->create(
            $tenantId,
            'Rappel standard',
            'reminder',
            'Rappel: {{meeting_title}} - {{meeting_date}}',
            self::DEFAULT_REMINDER_TEMPLATE,
            null,
            true,
            $createdBy
        );
        if ($rem) $created[] = $rem;

        return $created;
    }

    /**
     * Translates status to French.
     */
    private function translateStatus(string $status): string
    {
        $map = [
            'draft' => 'Brouillon',
            'scheduled' => 'Programme',
            'frozen' => 'Fige',
            'live' => 'En cours',
            'closed' => 'Termine',
            'validated' => 'Valide',
            'archived' => 'Archive',
        ];
        return $map[$status] ?? $status;
    }
}
