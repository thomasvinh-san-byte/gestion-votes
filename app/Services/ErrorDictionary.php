<?php
declare(strict_types=1);

namespace AgVote\Service;

/**
 * ErrorDictionary - French error message dictionary
 *
 * Centralizes API error code translations to provide
 * readable and consistent user messages.
 */
final class ErrorDictionary
{
    /**
     * Error messages in French
     * Format: code => message
     */
    private const MESSAGES = [
        // Authentication & Authorization
        'unauthorized' => 'Vous devez être connecté pour accéder à cette ressource.',
        'forbidden' => 'Vous n\'avez pas les droits nécessaires pour cette action.',
        'invalid_token' => 'Token d\'authentification invalide ou expiré.',
        'session_expired' => 'Votre session a expiré. Veuillez vous reconnecter.',
        'csrf_failed' => 'Jeton de sécurité invalide. Veuillez rafraîchir la page.',
        'role_required' => 'Cette action nécessite un rôle spécifique.',

        // General validation
        'validation_failed' => 'Les données soumises sont invalides.',
        'missing_required_field' => 'Un champ obligatoire est manquant.',
        'invalid_request' => 'La requête est mal formée ou incomplète.',
        'missing_or_invalid_uuid' => 'Un identifiant requis est manquant ou invalide.',
        'method_not_allowed' => 'Cette méthode HTTP n\'est pas autorisée pour cette ressource.',

        // Meetings
        'missing_meeting_id' => 'Identifiant de séance requis.',
        'meeting_not_found' => 'Séance introuvable.',
        'meeting_validated' => 'Cette séance est validée et ne peut plus être modifiée.',
        'meeting_archived' => 'Cette séance est archivée.',
        'meeting_locked' => 'Cette séance est verrouillée et ne peut pas être modifiée.',
        'meeting_not_validated' => 'Cette séance n\'est pas encore validée.',
        'meeting_not_live' => 'La séance n\'est pas en cours.',
        'meeting_already_started' => 'La séance a déjà commencé.',
        'meeting_already_live' => 'La séance est déjà en cours.',
        'meeting_not_closed' => 'La séance n\'est pas clôturée.',
        'meeting_invalid_transition' => 'Cette transition de statut n\'est pas autorisée.',

        // Motions
        'missing_motion_id' => 'Identifiant de résolution requis.',
        'motion_not_found' => 'Résolution introuvable.',
        'motion_already_opened' => 'Cette résolution est déjà ouverte au vote.',
        'motion_not_opened' => 'Cette résolution n\'est pas ouverte au vote.',
        'motion_already_closed' => 'Le vote sur cette résolution est déjà clos.',
        'motion_not_closed' => 'Le vote sur cette résolution n\'est pas encore clos.',
        'invalid_motion_id' => 'Identifiant de résolution invalide.',
        'missing_motion_ids' => 'Liste des résolutions requise.',

        // Membres
        'missing_member_id' => 'Identifiant de membre requis.',
        'member_not_found' => 'Membre introuvable.',
        'member_not_active' => 'Ce membre n\'est pas actif.',
        'member_already_exists' => 'Un membre avec cet email existe déjà.',
        'invalid_email' => 'Adresse email invalide.',

        // Attendance
        'member_not_present' => 'Ce membre n\'est pas enregistré comme présent.',
        'member_already_present' => 'Ce membre est déjà enregistré comme présent.',
        'invalid_attendance_mode' => 'Mode de présence invalide.',
        'attendance_not_found' => 'Enregistrement de présence introuvable.',

        // Vote
        'vote_not_allowed' => 'Vous n\'êtes pas autorisé à voter sur cette résolution.',
        'already_voted' => 'Vous avez déjà voté sur cette résolution.',
        'invalid_vote_choice' => 'Choix de vote invalide.',
        'vote_token_invalid' => 'Token de vote invalide ou expiré.',
        'vote_token_used' => 'Ce token de vote a déjà été utilisé.',
        'voting_closed' => 'Le vote est fermé.',
        'not_eligible' => 'Vous n\'êtes pas éligible au vote.',

        // Proxies
        'proxy_not_found' => 'Procuration introuvable.',
        'proxy_limit_exceeded' => 'Le nombre maximum de procurations est atteint.',
        'proxy_self_delegate' => 'Vous ne pouvez pas vous déléguer à vous-même.',
        'proxy_already_exists' => 'Une procuration existe déjà pour ce membre.',
        'proxy_chain_forbidden' => 'Les chaînes de procurations ne sont pas autorisées.',
        'invalid_proxy' => 'Procuration invalide.',

        // Policies
        'policy_not_found' => 'Politique de vote ou de quorum introuvable.',
        'quorum_not_met' => 'Le quorum n\'est pas atteint.',
        'invalid_policy' => 'Configuration de politique invalide.',

        // Exports & Reports
        'export_failed' => 'L\'export a échoué.',
        'report_generation_failed' => 'La génération du rapport a échoué.',
        'email_send_failed' => 'L\'envoi de l\'email a échoué.',

        // Tenant / Organization
        'tenant_not_found' => 'Organisation introuvable.',
        'invalid_tenant' => 'Organisation invalide.',

        // System errors
        'internal_error' => 'Une erreur interne est survenue. Veuillez réessayer.',
        'database_error' => 'Erreur de base de données. Veuillez réessayer.',
        'service_unavailable' => 'Service temporairement indisponible.',
        'maintenance_mode' => 'Le système est en maintenance.',

        // Generic business errors
        'business_error' => 'L\'opération n\'a pas pu être effectuée.',
        'conflict' => 'Conflit avec l\'état actuel de la ressource.',
        'precondition_failed' => 'Une condition préalable n\'est pas remplie.',
        'invalid_state' => 'L\'opération n\'est pas possible dans l\'état actuel.',
        'not_implemented' => 'Cette fonctionnalité n\'est pas encore disponible.',
    ];

    /**
     * Returns the French message for an error code
     */
    public static function getMessage(string $code): string
    {
        return self::MESSAGES[$code] ?? self::getDefaultMessage($code);
    }

    /**
     * Generates a default message for unmapped codes
     */
    private static function getDefaultMessage(string $code): string
    {
        // Transformer les underscores en espaces et capitaliser
        $readable = str_replace('_', ' ', $code);
        $readable = ucfirst($readable);
        return "Erreur: {$readable}.";
    }

    /**
     * Checks if an error code has a defined message
     */
    public static function hasMessage(string $code): bool
    {
        return isset(self::MESSAGES[$code]);
    }

    /**
     * Retourne tous les codes d'erreur disponibles
     */
    public static function getCodes(): array
    {
        return array_keys(self::MESSAGES);
    }

    /**
     * Enriches an error response with the translated message
     *
     * @param string $code Error code
     * @param array $extra Additional data (may contain 'detail')
     * @return array Response enriched with 'message'
     */
    public static function enrichError(string $code, array $extra = []): array
    {
        $message = self::getMessage($code);

        // Si un 'detail' est fourni, l'ajouter au message
        if (!empty($extra['detail']) && is_string($extra['detail'])) {
            $message .= ' ' . $extra['detail'];
        }

        return [
            'message' => $message,
        ] + $extra;
    }
}
