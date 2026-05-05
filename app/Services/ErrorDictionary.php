<?php

declare(strict_types=1);

namespace AgVote\Service;

/**
 * ErrorDictionary - French error message dictionary
 *
 * Centralizes API error code translations to provide
 * readable and consistent user messages.
 */
final class ErrorDictionary {
    /**
     * Error messages in French
     * Format: code => message
     */
    private const MESSAGES = [
        // Authentication & Authorization
        'unauthorized' => 'Vous devez être connecté pour accéder à cette ressource, reconnectez-vous depuis la page de connexion.',
        'forbidden' => 'Vous n\'avez pas les droits nécessaires pour cette action, demandez à un opérateur ou administrateur de l\'effectuer.',
        'invalid_token' => 'Ce lien n\'est plus valide ou a expiré, demandez à l\'opérateur un nouveau lien d\'accès.',
        'session_expired' => 'Votre session a expiré, reconnectez-vous depuis la page de connexion pour reprendre votre travail.',
        'csrf_failed' => 'Jeton de sécurité expiré, rechargez la page et recommencez l\'action.',
        'role_required' => 'Cette action nécessite un rôle spécifique, demandez à un administrateur de vous accorder les droits requis.',

        // General validation
        'validation_failed' => 'Les données soumises sont invalides, vérifiez les champs en erreur et corrigez-les avant de resoumettre.',
        'missing_required_field' => 'Un champ obligatoire est manquant, complétez les champs marqués d\'un astérisque.',
        'invalid_request' => 'La requête est mal formée ou incomplète, vérifiez les paramètres et recommencez l\'action.',
        'missing_or_invalid_uuid' => 'Un identifiant requis est manquant ou invalide, sélectionnez l\'élément depuis la liste pour générer un identifiant valide.',
        'method_not_allowed' => 'Cette méthode HTTP n\'est pas autorisée pour cette ressource, vérifiez l\'URL ou retournez à la page précédente.',

        // Meetings
        'missing_meeting_id' => 'Identifiant de séance requis, sélectionnez une séance dans la liste pour continuer.',
        'meeting_not_found' => 'Cette séance est introuvable, vérifiez le lien d\'accès ou retournez à la liste des séances.',
        'meeting_validated' => 'Cette séance est validée et ne peut plus être modifiée, créez une nouvelle séance si une correction est nécessaire.',
        'meeting_archived' => 'Cette séance est archivée, consultez-la en lecture seule depuis l\'espace archives.',
        'meeting_locked' => 'Cette séance est verrouillée et ne peut pas être modifiée, demandez à un administrateur de la déverrouiller.',
        'meeting_not_validated' => 'Cette séance n\'est pas encore validée, demandez au président de la valider avant cette action.',
        'meeting_not_live' => 'La séance n\'est pas en cours, demandez à l\'opérateur de la lancer pour permettre cette action.',
        'meeting_already_started' => 'La séance a déjà commencé, accédez directement au cockpit opérateur pour la piloter.',
        'meeting_already_live' => 'La séance est déjà en cours, accédez au cockpit opérateur pour la piloter.',
        'meeting_not_closed' => 'La séance n\'est pas clôturée, demandez à l\'opérateur de la clôturer avant cette action.',
        'meeting_invalid_transition' => 'Cette transition de statut n\'est pas autorisée, vérifiez le statut actuel et choisissez une transition compatible.',

        // Motions
        'missing_motion_id' => 'Identifiant de résolution requis, sélectionnez une résolution depuis l\'ordre du jour.',
        'motion_not_found' => 'Cette résolution est introuvable, vérifiez l\'ordre du jour de la séance.',
        'motion_already_opened' => 'Cette résolution est déjà ouverte au vote, accédez directement au scrutin en cours.',
        'motion_not_opened' => 'Cette résolution n\'est pas ouverte au vote, demandez à l\'opérateur de l\'ouvrir.',
        'motion_already_closed' => 'Le vote sur cette résolution est déjà clos, consultez les résultats dans le rapport de séance.',
        'motion_not_closed' => 'Le vote sur cette résolution n\'est pas encore clos, demandez à l\'opérateur de le clore avant cette action.',
        'invalid_motion_id' => 'Identifiant de résolution invalide, sélectionnez une résolution depuis l\'ordre du jour.',
        'missing_motion_ids' => 'Liste des résolutions requise, sélectionnez au moins une résolution avant de soumettre.',

        // Membres
        'missing_member_id' => 'Identifiant de membre requis, sélectionnez un membre dans le registre.',
        'member_not_found' => 'Ce membre est introuvable, vérifiez le registre des inscrits ou actualisez la page.',
        'member_not_active' => 'Ce membre n\'est pas actif, demandez à un administrateur de réactiver son compte.',
        'member_already_exists' => 'Un membre avec cet email existe déjà, vérifiez le registre ou utilisez une autre adresse.',
        'invalid_email' => 'Adresse email invalide, vérifiez la syntaxe (format prenom@domaine.fr) avant de resoumettre.',

        // Attendance
        'member_not_present' => 'Ce membre n\'est pas enregistré comme présent, demandez à l\'opérateur de marquer sa présence avant cette action.',
        'member_already_present' => 'Ce membre est déjà enregistré comme présent, vérifiez la feuille de présence.',
        'invalid_attendance_mode' => 'Mode de présence invalide, sélectionnez parmi : présent, représenté, ou absent.',
        'attendance_not_found' => 'Enregistrement de présence introuvable, actualisez la feuille de présence ou marquez à nouveau.',

        // Vote
        'vote_not_allowed' => 'Vous n\'êtes pas autorisé à voter sur cette résolution, demandez à l\'opérateur de vérifier votre éligibilité.',
        'already_voted' => 'Vous avez déjà voté sur cette résolution, demandez à l\'opérateur d\'annuler votre vote précédent pour le modifier.',
        'invalid_vote_choice' => 'Choix de vote invalide, sélectionnez Pour, Contre, ou Abstention parmi les options disponibles.',
        'vote_token_invalid' => 'Ce lien de vote n\'est plus valide, demandez à l\'opérateur un nouveau lien.',
        'vote_token_used' => 'Ce lien de vote a déjà été utilisé, consultez votre vote dans le récapitulatif ou demandez à l\'opérateur en cas d\'erreur.',
        'voting_closed' => 'Le vote est fermé, consultez les résultats dans le rapport de séance.',
        'not_eligible' => 'Vous n\'êtes pas éligible au vote, vérifiez auprès de l\'opérateur si votre inscription est à jour.',

        // Proxies
        'proxy_not_found' => 'Procuration introuvable, vérifiez la liste des procurations enregistrées ou recréez-la.',
        'proxy_limit_exceeded' => 'Le nombre maximum de procurations est atteint, demandez à un administrateur d\'ajuster la politique de la séance.',
        'proxy_self_delegate' => 'Vous ne pouvez pas vous déléguer à vous-même, sélectionnez un autre membre comme mandataire.',
        'proxy_already_exists' => 'Une procuration existe déjà pour ce membre, supprimez la procuration existante avant d\'en créer une nouvelle.',
        'proxy_chain_forbidden' => 'Les chaînes de procurations ne sont pas autorisées, sélectionnez un mandataire qui n\'est pas lui-même mandant.',
        'invalid_proxy' => 'Procuration invalide, vérifiez le mandant et le mandataire avant de soumettre à nouveau.',

        // Policies
        'policy_not_found' => 'Politique de vote ou de quorum introuvable, sélectionnez une politique existante ou demandez à un administrateur d\'en créer une.',
        'quorum_not_met' => 'Le quorum n\'est pas atteint, attendez davantage de votants ou demandez au président de reporter le scrutin.',
        'invalid_policy' => 'Configuration de politique invalide, vérifiez les seuils et types avant de resoumettre.',

        // Exports & Reports
        'export_failed' => 'L\'export a échoué, vérifiez le format demandé et relancez l\'opération depuis l\'écran exports.',
        'report_generation_failed' => 'La génération du rapport a échoué, vérifiez que la séance est clôturée et relancez la génération.',
        'email_send_failed' => 'L\'envoi de l\'email a échoué, vérifiez la configuration SMTP avec un administrateur avant de renvoyer.',

        // Tenant / Organization
        'tenant_not_found' => 'Organisation introuvable, reconnectez-vous ou vérifiez le sous-domaine d\'accès.',
        'invalid_tenant' => 'Organisation invalide, reconnectez-vous depuis le bon sous-domaine de votre organisation.',

        // System errors
        'server_error' => 'Erreur serveur, contactez un administrateur en transmettant l\'identifiant de requête affiché dans la console.',
        'internal_error' => 'Erreur interne du serveur, contactez un administrateur en transmettant l\'identifiant de requête affiché dans la console.',
        'database_error' => 'Erreur de base de données, contactez un administrateur en transmettant l\'identifiant de requête affiché dans la console.',
        'service_unavailable' => 'Service temporairement indisponible, attendez quelques instants puis rechargez la page.',
        'maintenance_mode' => 'Le système est en maintenance, consultez les annonces de votre organisation pour la fin prévue de l\'intervention.',

        // Generic business errors
        'business_error' => 'L\'opération n\'a pas pu être effectuée, vérifiez les prérequis de l\'action ou consultez le détail affiché.',
        // Specific business errors (Plan 02.1 — migration depuis business_error)
        'meeting_transition_failed' => 'La transition de séance a échoué, vérifiez le statut courant et les pré-requis du workflow ou consultez le détail affiché.',
        'meeting_operation_failed' => 'L\'opération sur la séance a échoué, vérifiez les pré-requis affichés ou actualisez la page avant de relancer l\'action.',
        'meeting_state_read_failed' => 'Lecture de l\'état de séance impossible, actualisez la page ou vérifiez auprès de l\'opérateur que la séance est encore active.',
        'conflict' => 'Conflit avec l\'état actuel de la ressource, actualisez la page pour récupérer la dernière version puis recommencez.',
        'precondition_failed' => 'Une condition préalable n\'est pas remplie, vérifiez les prérequis affichés avant de renouveler l\'action.',
        'invalid_state' => 'L\'opération n\'est pas possible dans l\'état actuel, vérifiez le statut de la ressource avant de recommencer.',

        // ERR-V26-01: codes ciblés extraits de RuntimeException via AbstractController
        'archived_meeting_locked' => 'Séance archivée : aucune transition ni modification autorisée, créez une nouvelle séance pour repartir d\'un état modifiable ou consultez l\'archive en lecture seule.',
        'validated_meeting_locked' => 'Séance validée : la réinitialisation est interdite pour préserver l\'audit, créez une nouvelle séance si vous devez recommencer le processus de vote.',

        // Authentication (suppléments)
        'invalid_credentials' => 'Identifiants invalides, vérifiez votre email et mot de passe ou réinitialisez-le depuis le formulaire de connexion.',
        'missing_credentials' => 'Identifiants manquants, complétez votre email et mot de passe avant de soumettre.',
        'account_disabled' => 'Compte désactivé, demandez à un administrateur de réactiver votre accès.',
        'missing_or_invalid_api_key' => 'Clé d\'authentification manquante ou invalide, demandez une nouvelle clé à un administrateur.',
        'user_inactive' => 'Compte utilisateur inactif, demandez à un administrateur de réactiver votre accès.',

        // Validation (suppléments)
        'missing_id' => 'Identifiant requis, sélectionnez l\'élément depuis la liste pour générer un identifiant valide.',
        'invalid_id' => 'Identifiant invalide, sélectionnez l\'élément depuis la liste ou actualisez la page pour récupérer un identifiant valide.',
        'invalid_meeting_id' => 'Identifiant de séance invalide, sélectionnez une séance depuis la liste pour récupérer un identifiant correct.',
        'invalid_member_id' => 'Identifiant de membre invalide, sélectionnez un membre depuis le registre pour récupérer un identifiant correct.',
        'missing_meeting_or_email' => 'Séance et adresse email requis.',
        'missing_meeting_or_member' => 'Séance et membre requis.',
        'missing_fields' => 'Champs obligatoires manquants.',
        'missing_title' => 'Le titre est obligatoire.',
        'title_too_long' => 'Titre trop long.',
        'president_name_too_long' => 'Nom du président trop long.',
        'invalid_meeting_type' => 'Type de séance invalide.',
        'missing_device_id' => 'Identifiant d\'appareil requis.',
        'missing_reason' => 'Une justification est requise.',
        'missing_justification' => 'Une justification est requise, complétez le champ motif avant de soumettre l\'action.',

        // Meeting workflow (suppléments)
        'meeting_archived_locked' => 'Séance archivée : modification interdite.',
        'status_via_transition' => 'Utilisez la transition de statut dédiée.',
        'already_in_status' => 'La séance est déjà dans ce statut.',
        'invalid_launch_status' => 'La séance ne peut pas être lancée dans ce statut.',
        'launch_failed' => 'Échec du lancement de la séance.',
        'workflow_issues' => 'Problèmes de workflow détectés, consultez la liste des incidents affichés et corrigez chaque point bloquant avant de relancer.',
        'consolidate_failed' => 'Échec de la consolidation.',

        // Motions (suppléments)
        'motion_not_open' => 'Cette résolution n\'est pas ouverte au vote, demandez à l\'opérateur de l\'ouvrir depuis le cockpit avant de soumettre votre choix.',
        'motion_closed' => 'Le vote sur cette résolution est clos, consultez les résultats dans le rapport de séance ou passez à la résolution suivante.',

        // Vote (suppléments)
        'invalid_vote' => 'Vote invalide.',
        'cancel_failed' => 'Échec de l\'annulation.',
        'not_manual_vote' => 'Ce vote n\'est pas un vote manuel.',
        'ballot_not_found' => 'Bulletin introuvable.',

        // Members (suppléments)
        'invalid_member_ids' => 'Identifiants de membres invalides, sélectionnez les membres depuis le registre pour récupérer des identifiants corrects.',
        'no_members' => 'Aucun membre à traiter, vérifiez votre sélection ou complétez le registre avant de relancer l\'action.',
        'group_not_found' => 'Groupe introuvable, vérifiez la liste des groupes ou demandez à un administrateur de le recréer.',
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
        'invalid_template_id' => 'Identifiant de modèle invalide, sélectionnez un modèle depuis la liste pour récupérer un identifiant correct.',
        'template_not_found' => 'Modèle introuvable, vérifiez la liste des modèles disponibles ou demandez à un administrateur de le recréer.',
        'invalid_template_type' => 'Type de modèle invalide, sélectionnez un type parmi les valeurs proposées avant de soumettre.',
        'template_name_exists' => 'Un modèle avec ce nom existe déjà, choisissez un nom différent ou modifiez le modèle existant.',
        'unknown_variables' => 'Variables inconnues dans le modèle, vérifiez la liste des variables autorisées affichée dans l\'éditeur et corrigez le contenu.',
        'missing_name' => 'Le nom est obligatoire.',
        'missing_subject' => 'Le sujet est obligatoire.',
        'missing_body_html' => 'Le contenu HTML est obligatoire, complétez le corps du modèle dans l\'éditeur avant de sauvegarder.',
        'missing_new_name' => 'Le nouveau nom est obligatoire.',
        'cannot_delete_default' => 'Impossible de supprimer le modèle par défaut.',
        'duplicate_failed' => 'Échec de la duplication, vérifiez vos droits sur l\'élément source puis relancez l\'opération.',
        'create_failed' => 'Échec de la création, vérifiez les champs obligatoires et relancez l\'opération.',
        'update_failed' => 'Échec de la mise à jour, vérifiez vos modifications et relancez l\'enregistrement.',
        'delete_failed' => 'Échec de la suppression, vérifiez que l\'élément n\'est pas verrouillé puis relancez l\'opération.',

        // Export templates
        'invalid_export_type' => 'Type d\'export invalide, sélectionnez un format parmi les valeurs proposées avant de relancer.',
        'name_already_exists' => 'Ce nom existe déjà, choisissez un nom différent avant de soumettre.',
        'creation_failed' => 'Échec de la création, vérifiez les champs obligatoires et relancez l\'opération.',
        'invalid_name' => 'Nom invalide, complétez le champ avec un libellé non vide avant de soumettre.',

        // File upload
        'upload_error' => 'Erreur lors de l\'upload du fichier, vérifiez votre connexion réseau puis sélectionnez à nouveau le fichier à importer.',
        'invalid_file_type' => 'Type de fichier invalide, sélectionnez un fichier au format autorisé indiqué dans la zone d\'import.',
        'file_too_large' => 'Fichier trop volumineux, compressez le contenu ou découpez-le avant de relancer l\'import.',
        'invalid_mime_type' => 'Type MIME non autorisé, vérifiez le format du fichier puis sélectionnez un fichier au format attendu.',
        'file_read_error' => 'Erreur de lecture du fichier, vérifiez que le fichier n\'est pas corrompu puis sélectionnez-le à nouveau.',
        'invalid_csv' => 'Fichier CSV invalide, vérifiez les colonnes attendues puis exportez à nouveau le fichier source.',
        'import_failed' => 'Échec de l\'import, vérifiez le format du fichier et relancez l\'opération depuis l\'écran d\'import.',

        // SMTP / Email
        'smtp_not_configured' => 'Le serveur SMTP n\'est pas configuré, demandez à un administrateur de renseigner les paramètres SMTP avant de relancer l\'envoi.',
        'mail_send_failed' => 'Échec de l\'envoi de l\'email, vérifiez la configuration SMTP avec un administrateur avant de renvoyer le message.',

        // Admin
        'invalid_meeting_role' => 'Rôle de séance invalide.',
        'admin_required_for_president' => 'Droits administrateur requis pour cette action.',
        'unknown_action' => 'Action inconnue.',
        'endpoint_disabled' => 'Ce point d\'accès est désactivé.',
        'missing_type' => 'Le type est obligatoire.',
        'missing_procedure_code' => 'Code de procédure manquant.',
        'invalid_item_index' => 'Index d\'élément invalide.',
        'missing_meeting_id_or_motion' => 'Séance ou résolution manquante.',
        'invalid_source_id' => 'Identifiant source invalide, sélectionnez l\'élément source depuis la liste pour récupérer un identifiant correct.',

        // Ballots (suppléments)
        'invalid_code' => 'Code invalide.',
        'invalid_vote_token' => 'Token de vote invalide ou expiré.',
        'invalid_vote_value' => 'Valeur de vote invalide.',
        'invalid_weight' => 'Poids de vote invalide.',
        'missing_kind' => 'Type d\'incident requis.',
        'paper_ballot_not_found_or_used' => 'Bulletin papier introuvable ou déjà utilisé.',
        'token_member_mismatch' => 'Le token ne correspond pas à ce votant.',
        'token_motion_mismatch' => 'Le token ne correspond pas à cette résolution.',

        // Motions (suppléments)
        'agenda_mismatch' => 'L\'ordre du jour ne correspond pas.',
        'agenda_not_found' => 'Ordre du jour introuvable.',
        'another_motion_active' => 'Une autre résolution est déjà en cours de vote.',
        'description_too_long' => 'Description trop longue.',
        'inconsistent_tally' => 'Le décompte des voix est incohérent.',
        'invalid_numbers' => 'Les valeurs numériques sont invalides.',
        'invalid_total' => 'Le total est invalide.',
        'motion_active_locked' => 'Résolution en cours de vote : modification interdite.',
        'motion_closed_locked' => 'Résolution clôturée : modification interdite.',
        'motion_open_locked' => 'Résolution ouverte : modification interdite.',
        'vote_exceeds_total' => 'Le nombre de votes dépasse le total.',
        'vote_policy_not_found' => 'Politique de vote introuvable.',

        // Admin (suppléments)
        'cannot_delete_self' => 'Vous ne pouvez pas supprimer votre propre compte.',
        'cannot_demote_self' => 'Vous ne pouvez pas retirer vos propres droits.',
        'cannot_toggle_self' => 'Vous ne pouvez pas modifier votre propre statut.',
        'email_exists' => 'Un utilisateur avec cette adresse email existe déjà.',
        'invalid_role' => 'Rôle invalide.',
        'weak_password' => 'Mot de passe trop faible.',

        // Meetings (suppléments)
        'archived_immutable' => 'Séance archivée : aucune modification possible, créez une nouvelle séance si une correction est nécessaire ou consultez l\'archive en lecture seule.',
        'force_requires_admin' => 'Le forçage de transition nécessite les droits administrateur.',
        'invalid_status' => 'Statut invalide.',
        'invalid_status_for_consolidation' => 'Statut invalide pour la consolidation.',
        'invalid_vote_policy_id' => 'Identifiant de politique de vote invalide.',
        'meeting_not_draft' => 'La séance doit être en brouillon pour cette opération.',
        'meeting_validated_locked' => 'Séance validée : modification interdite.',
        'missing_confirm' => 'Confirmation requise.',
        'missing_president_name' => 'Le nom du président est obligatoire.',
        'missing_to_status' => 'Le statut cible est obligatoire.',
        'no_live_meeting' => 'Aucune séance en cours, demandez à l\'opérateur de lancer une séance avant de relancer cette action.',
        'no_motion_to_open' => 'Aucune résolution à ouvrir.',

        // Import
        'invalid_file' => 'Fichier invalide, vérifiez le format et le contenu du fichier avant de le sélectionner à nouveau.',
        'missing_columns' => 'Colonnes requises manquantes.',
        'missing_name_column' => 'La colonne nom est obligatoire.',
        'missing_title_column' => 'La colonne titre est obligatoire.',

        // Analytics
        'invalid_format' => 'Format invalide.',
        'invalid_report_type' => 'Type de rapport invalide.',
        'invalid_type' => 'Type invalide.',

        // Proxies (suppléments)
        'invalid_receiver_member_id' => 'Identifiant du mandataire invalide, sélectionnez un membre dans le registre pour récupérer un identifiant correct.',
        'missing_proxy_id' => 'Identifiant de procuration requis, sélectionnez la procuration concernée dans la liste avant de soumettre l\'action.',

        // Reminders
        'invalid_days_before' => 'Nombre de jours invalide.',
        'invalid_reminder_id' => 'Identifiant de rappel invalide.',
        'invalid_send_time' => 'Heure d\'envoi invalide.',
        'reminder_not_found' => 'Rappel introuvable.',

        // Email (suppléments)
        'invalid_scheduled_at' => 'Date de programmation invalide.',

        // Member groups
        'invalid_group_id' => 'Identifiant de groupe invalide.',

        // Speech
        'invalid_uuid' => 'UUID invalide, sélectionnez l\'élément depuis la liste pour récupérer un identifiant correct.',

        // Invitations (suppléments)
        'missing_token' => 'Token requis.',

        // Auth (suppléments)
        'rate_limit_exceeded' => 'Trop de requêtes. Veuillez réessayer dans quelques instants.',

        // Generic
        'not_found' => 'Ressource introuvable, vérifiez le lien d\'accès ou actualisez la page pour récupérer la liste à jour.',
    ];

    /**
     * Returns the French message for an error code
     */
    public static function getMessage(string $code): string {
        return self::MESSAGES[$code] ?? self::getDefaultMessage($code);
    }

    /**
     * Generates a default message for unmapped codes
     */
    private static function getDefaultMessage(string $code): string {
        // Transformer les underscores en espaces et capitaliser
        $readable = str_replace('_', ' ', $code);
        $readable = ucfirst($readable);
        return "Erreur: {$readable}.";
    }

    /**
     * Checks if an error code has a defined message
     */
    public static function hasMessage(string $code): bool {
        return isset(self::MESSAGES[$code]);
    }

    /**
     * Retourne tous les codes d'erreur disponibles
     */
    public static function getCodes(): array {
        return array_keys(self::MESSAGES);
    }

    /**
     * Enriches an error response with the translated message
     *
     * @param string $code Error code
     * @param array $extra Additional data (may contain 'detail')
     *
     * @return array Response enriched with 'message'
     */
    public static function enrichError(string $code, array $extra = []): array {
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
