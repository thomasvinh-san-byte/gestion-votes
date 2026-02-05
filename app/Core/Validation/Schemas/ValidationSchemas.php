<?php
declare(strict_types=1);

namespace AgVote\Core\Validation\Schemas;

use AgVote\Core\Validation\InputValidator;

/**
 * ValidationSchemas - Pre-built validation schemas for entities
 *
 * Usage:
 *   $result = ValidationSchemas::meeting()->validate($input);
 *   $result = ValidationSchemas::motion()->validate($input);
 */
final class ValidationSchemas
{
    /**
     * Schéma pour création/mise à jour de séance (meeting)
     */
    public static function meeting(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->string('title')
            ->minLength(3)
            ->maxLength(255)
            ->required();
        
        $schema->enum('meeting_type', [
            'ag_ordinaire',
            'ag_extraordinaire',
            'conseil',
            'bureau',
            'autre'
        ])->default('ag_ordinaire');
        
        $schema->enum('status', [
            'draft',
            'preparation',
            'live',
            'closed',
            'validated',
            'archived'
        ])->default('draft');
        
        $schema->datetime('scheduled_at')
            ->optional();
        
        $schema->string('location')
            ->maxLength(255)
            ->optional();
        
        $schema->string('description')
            ->maxLength(5000)
            ->optional();
        
        $schema->uuid('quorum_policy_id')
            ->optional();
        
        $schema->uuid('vote_policy_id')
            ->optional();
        
        $schema->integer('convocation_no')
            ->min(1)
            ->max(10)
            ->default(1);
        
        $schema->string('president_name')
            ->maxLength(255)
            ->optional();
        
        $schema->uuid('president_member_id')
            ->optional();
        
        return $schema;
    }

    /**
     * Schéma pour création/mise à jour de motion (résolution)
     */
    public static function motion(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('meeting_id')
            ->required();
        
        $schema->string('title')
            ->minLength(3)
            ->maxLength(500)
            ->required();
        
        $schema->string('description')
            ->maxLength(10000)
            ->optional();
        
        $schema->integer('position')
            ->min(0)
            ->max(1000)
            ->optional();
        
        $schema->boolean('secret')
            ->default(false);
        
        $schema->uuid('quorum_policy_id')
            ->optional();
        
        $schema->uuid('vote_policy_id')
            ->optional();
        
        $schema->enum('status', [
            'draft',
            'open',
            'closed',
            'tallied'
        ])->default('draft');
        
        return $schema;
    }

    /**
     * Schéma pour création/mise à jour de membre
     */
    public static function member(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->string('full_name')
            ->minLength(2)
            ->maxLength(255)
            ->required();
        
        $schema->email('email')
            ->optional();
        
        $schema->number('voting_power')
            ->min(0)
            ->max(1000000)
            ->default(1);
        
        $schema->boolean('is_active')
            ->default(true);
        
        $schema->string('role')
            ->maxLength(50)
            ->optional();
        
        $schema->string('address')
            ->maxLength(500)
            ->optional();
        
        $schema->string('phone')
            ->maxLength(50)
            ->optional();
        
        return $schema;
    }

    /**
     * Schéma pour enregistrement de vote (ballot)
     */
    public static function ballot(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('motion_id')
            ->required();
        
        $schema->uuid('member_id')
            ->required();
        
        $schema->enum('value', [
            'for',
            'against',
            'abstain',
            'nsp',
            'pour',
            'contre',
            'abstention',
            'blanc'
        ])->required();
        
        $schema->number('weight')
            ->min(0)
            ->max(1000000)
            ->optional();
        
        $schema->boolean('is_proxy_vote')
            ->default(false);
        
        $schema->uuid('proxy_source_member_id')
            ->optional();
        
        return $schema;
    }

    /**
     * Schéma pour enregistrement de présence (attendance)
     */
    public static function attendance(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('meeting_id')
            ->required();
        
        $schema->uuid('member_id')
            ->required();
        
        $schema->enum('mode', [
            'present',
            'remote',
            'proxy',
            'absent'
        ])->required();
        
        $schema->datetime('checked_in_at')
            ->optional();
        
        $schema->datetime('checked_out_at')
            ->optional();
        
        return $schema;
    }

    /**
     * Schéma pour création de procuration (proxy)
     */
    public static function proxy(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('meeting_id')
            ->required();
        
        $schema->uuid('giver_member_id')
            ->required();
        
        $schema->uuid('receiver_member_id')
            ->required();
        
        $schema->enum('scope', [
            'full',
            'agenda_items'
        ])->default('full');
        
        $schema->array('agenda_item_ids')
            ->optional();
        
        $schema->datetime('valid_from')
            ->optional();
        
        $schema->datetime('valid_until')
            ->optional();
        
        return $schema;
    }

    /**
     * Schéma pour politique de quorum
     */
    public static function quorumPolicy(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->string('name')
            ->minLength(2)
            ->maxLength(100)
            ->required();
        
        $schema->string('description')
            ->maxLength(500)
            ->optional();
        
        $schema->enum('mode', [
            'single',
            'evolving',
            'double'
        ])->default('single');
        
        $schema->enum('denominator', [
            'eligible_members',
            'eligible_weight'
        ])->default('eligible_members');
        
        $schema->number('threshold')
            ->min(0)
            ->max(1)
            ->required();
        
        $schema->number('threshold_call2')
            ->min(0)
            ->max(1)
            ->optional();
        
        $schema->enum('denominator2', [
            'eligible_members',
            'eligible_weight'
        ])->optional();
        
        $schema->number('threshold2')
            ->min(0)
            ->max(1)
            ->optional();
        
        $schema->boolean('include_proxies')
            ->default(true);
        
        $schema->boolean('count_remote')
            ->default(true);
        
        return $schema;
    }

    /**
     * Schéma pour politique de vote
     */
    public static function votePolicy(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->string('name')
            ->minLength(2)
            ->maxLength(100)
            ->required();
        
        $schema->string('description')
            ->maxLength(500)
            ->optional();
        
        $schema->enum('base', [
            'expressed',
            'present',
            'eligible'
        ])->default('expressed');
        
        $schema->number('threshold')
            ->min(0)
            ->max(1)
            ->required();
        
        $schema->boolean('abstention_as_against')
            ->default(false);
        
        return $schema;
    }

    /**
     * Schéma pour validation de séance (par président)
     */
    public static function meetingValidation(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('meeting_id')
            ->required();
        
        $schema->string('president_name')
            ->minLength(2)
            ->maxLength(255)
            ->required();
        
        $schema->uuid('president_member_id')
            ->optional();
        
        $schema->string('notes')
            ->maxLength(5000)
            ->optional();
        
        return $schema;
    }

    /**
     * Schéma pour saisie manuelle (mode dégradé)
     */
    public static function degradedTally(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('motion_id')
            ->required();
        
        $schema->integer('manual_total')
            ->min(0)
            ->max(100000)
            ->required();
        
        $schema->integer('manual_for')
            ->min(0)
            ->max(100000)
            ->required();
        
        $schema->integer('manual_against')
            ->min(0)
            ->max(100000)
            ->required();
        
        $schema->integer('manual_abstain')
            ->min(0)
            ->max(100000)
            ->default(0);
        
        $schema->string('justification')
            ->minLength(10)
            ->maxLength(1000)
            ->required();
        
        return $schema;
    }

    /**
     * Schéma pour création d'incident
     */
    public static function incident(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('meeting_id')
            ->required();
        
        $schema->uuid('motion_id')
            ->optional();
        
        $schema->enum('type', [
            'network',
            'hardware',
            'software',
            'human_error',
            'security',
            'other'
        ])->required();
        
        $schema->string('description')
            ->minLength(10)
            ->maxLength(2000)
            ->required();
        
        $schema->enum('severity', [
            'low',
            'medium',
            'high',
            'critical'
        ])->default('medium');
        
        $schema->string('resolution')
            ->maxLength(2000)
            ->optional();
        
        return $schema;
    }

    /**
     * Schéma pour création d'utilisateur (RBAC)
     */
    public static function user(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->email('email')
            ->required();
        
        $schema->string('name')
            ->minLength(2)
            ->maxLength(255)
            ->required();
        
        $schema->enum('role', [
            'admin',
            'operator',
            'president',
            'trust',
            'readonly'
        ])->required();
        
        $schema->boolean('is_active')
            ->default(true);
        
        return $schema;
    }

    /**
     * Schéma pour demande de parole (speech)
     */
    public static function speechRequest(): InputValidator
    {
        $schema = InputValidator::schema();
        
        $schema->uuid('meeting_id')
            ->required();
        
        $schema->uuid('member_id')
            ->required();
        
        $schema->enum('status', [
            'waiting',
            'speaking',
            'finished',
            'cancelled'
        ])->default('waiting');
        
        return $schema;
    }
}
