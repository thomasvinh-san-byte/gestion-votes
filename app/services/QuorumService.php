<?php
/**
 * QuorumService - Calcul de la validité juridique de la séance
 * Standard : PSR-12, Strict Types, PDO Prepared Statements
 */
declare(strict_types=1);

class QuorumService {
    public static function calculate(PDO $db, int $meetingId): array {
        // 1. Récupération sécurisée du modèle
        $stmt = $db->prepare("
            SELECT qm.* 
            FROM meeting_quorum mq 
            JOIN quorum_models qm ON mq.quorum_model_code = qm.code 
            WHERE mq.meeting_id = :m
        ");
        $stmt->execute(['m' => $meetingId]);
        $model = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$model) {
            return ['reached' => false, 'justification' => 'Configuration du quorum manquante pour cette séance.'];
        }

        // 2. Calcul basé sur le type de base (Personnes vs Poids/Tantièmes)
        if ($model['base'] === 'persons') {
            $sql = "SELECT COUNT(*) FROM attendances WHERE meeting_id = :m AND status IN ('present','represented')";
        } else {
            $sql = "SELECT COALESCE(SUM(m.weight), 0) 
                    FROM members m 
                    JOIN attendances a ON a.member_id = m.id 
                    WHERE a.meeting_id = :m AND a.status IN ('present','represented')";
        }

        $stmtCalc = $db->prepare($sql);
        $stmtCalc->execute(['m' => $meetingId]);
        $currentValue = (float)$stmtCalc->fetchColumn();

        // 3. Logique de décision (Comparaison stricte)
        $threshold = (float)$model['threshold'];
        $reached = ($currentValue >= $threshold);

        return [
            'reached' => $reached,
            'current' => $currentValue,
            'threshold' => $threshold,
            'unit' => ($model['base'] === 'persons' ? 'pers.' : 'tantièmes'),
            'justification' => sprintf(
                "Quorum %s : %.2f / %.2f %s (%s)",
                $model['label'],
                $currentValue,
                $threshold,
                ($model['base'] === 'persons' ? 'pers.' : 'pts'),
                $reached ? 'Atteint' : 'Insuffisant'
            )
        ];
    }
}