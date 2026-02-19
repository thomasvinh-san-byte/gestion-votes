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
        'server_error' => 'Erreur serveur. Consultez les logs pour plus de détails.',
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

        // Authentication (suppléments)
        'invalid_credentials' => 'Identifiants invalides.',
        'missing_credentials' => 'Identifiants manquants.',
        'account_disabled' => 'Compte désactivé.',
        'missing_or_invalid_api_key' => 'Clé d\'authentification manquante ou invalide.',
        'user_inactive' => 'Compte utilisateur inactif.',

        // Validation (suppléments)
        'missing_id' => 'Identifiant requis.',
        'invalid_id' => 'Identifiant invalide.',
        'invalid_meeting_id' => 'Identifiant de séance invalide.',
        'invalid_member_id' => 'Identifiant de membre invalide.',
        'missing_meeting_or_email' => 'Séance et adresse email requis.',
        'missing_meeting_or_member' => 'Séance et membre requis.',
        'missing_fields' => 'Champs obligatoires manquants.',
        'missing_title' => 'Le titre est obligatoire.',
        'title_too_long' => 'Titre trop long.',
        'president_name_too_long' => 'Nom du président trop long.',
        'invalid_meeting_type' => 'Type de séance invalide.',
        'missing_device_id' => 'Identifiant d\'appareil requis.',
        'missing_reason' => 'Une justification est requise.',
        'missing_justification' => 'Une justification est requise.',

        // Meeting workflow (suppléments)
        'meeting_archived_locked' => 'Séance archivée : modification interdite.',
        'status_via_transition' => 'Utilisez la transition de statut dédiée.',
        'already_in_status' => 'La séance est déjà dans ce statut.',
        'invalid_launch_status' => 'La séance ne peut pas être lancée dans ce statut.',
        'launch_failed' => 'Échec du lancement de la séance.',
        'workflow_issues' => 'Problèmes de workflow détectés.',
        'consolidate_failed' => 'Échec de la consolidation.',

        // Motions (suppléments)
        'motion_not_open' => 'Cette résolution n\'est pas ouverte au vote.',
        'motion_closed' => 'Le vote sur cette résolution est clos.',

        // Vote (suppléments)
        'invalid_vote' => 'Vote invalide.',
        'missing_motion_ids' => 'Liste des résolutions requise.',
        'cancel_failed' => 'Échec de l\'annulation.',
        'not_manual_vote' => 'Ce vote n\'est pas un vote manuel.',
        'ballot_not_found' => 'Bulletin introuvable.',

        // Members (suppléments)
        'invalid_member_ids' => 'Identifiants de membres invalides.',
        'no_members' => 'Aucun membre à traiter.',
        'group_not_found' => 'Groupe introuvable.',
        'user_not_found' => 'Utilisateur introuvable.',

        // Attendance (suppléments)
        'invalid_mode' => 'Mode invalide.',
        'missing_identifier' => 'Identifiant manquant.',

        // Invitations (suppléments)
        'token_not_usable' => 'Ce jeton n\'est plus utilisable.',

        // Policies (suppléments)
        'invalid_quorum_policy_id' => 'Identifiant de politique de quorum invalide.',
        'invalid_convocation_no' => 'Numéro de convocation invalide.',
        'quorum_policy_not_found' => 'Politique de quorum introuvable.',

        // Email templates
        'invalid_template_id' => 'Identifiant de modèle invalide.',
        'template_not_found' => 'Modèle introuvable.',
        'invalid_template_type' => 'Type de modèle invalide.',
        'template_name_exists' => 'Un modèle avec ce nom existe déjà.',
        'unknown_variables' => 'Variables inconnues dans le modèle.',
        'missing_name' => 'Le nom est obligatoire.',
        'missing_subject' => 'Le sujet est obligatoire.',
        'missing_body_html' => 'Le contenu HTML est obligatoire.',
        'missing_new_name' => 'Le nouveau nom est obligatoire.',
        'cannot_delete_default' => 'Impossible de supprimer le modèle par défaut.',
        'duplicate_failed' => 'Échec de la duplication.',
        'create_failed' => 'Échec de la création.',
        'update_failed' => 'Échec de la mise à jour.',
        'delete_failed' => 'Échec de la suppression.',

        // Export templates
        'invalid_export_type' => 'Type d\'export invalide.',
        'name_already_exists' => 'Ce nom existe déjà.',
        'creation_failed' => 'Échec de la création.',
        'invalid_name' => 'Nom invalide.',

        // File upload
        'upload_error' => 'Erreur lors de l\'upload du fichier.',
        'invalid_file_type' => 'Type de fichier invalide.',
        'file_too_large' => 'Fichier trop volumineux.',
        'invalid_mime_type' => 'Type MIME non autorisé.',
        'file_read_error' => 'Erreur de lecture du fichier.',
        'invalid_csv' => 'Fichier CSV invalide.',
        'import_failed' => 'Échec de l\'import.',

        // SMTP / Email
        'smtp_not_configured' => 'Le serveur SMTP n\'est pas configuré.',
        'mail_send_failed' => 'Échec de l\'envoi de l\'email.',

        // Admin
        'invalid_meeting_role' => 'Rôle de séance invalide.',
        'admin_required_for_president' => 'Droits administrateur requis pour cette action.',
        'unknown_action' => 'Action inconnue.',
        'endpoint_disabled' => 'Ce point d\'accès est désactivé.',
        'missing_type' => 'Le type est obligatoire.',
        'missing_procedure_code' => 'Code de procédure manquant.',
        'invalid_item_index' => 'Index d\'élément invalide.',
        'missing_meeting_id_or_motion' => 'Séance ou résolution manquante.',
        'invalid_source_id' => 'Identifiant source invalide.',
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
