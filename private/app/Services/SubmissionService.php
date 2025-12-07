<?php
namespace App\Services;

/**
 * SubmissionService — TEST MODE with embedded secrets/configs
 *
 * Uses hard-coded DB_PATH and relies on MistralShortAnswerGrader and RobloxPromotionService.
 *
 * WARNING: This file contains test secrets/paths embedded for immediate testing only.
 */

/* --- TEST / DEV hard-coded configuration (do NOT commit to VCS) --- */
$TEST_DB_PATH = '/home/NolanI/web/bulletproof.astroyds.com/private/storage/applications.db';
/* ---------------------------------------------------------------- */

class SubmissionService
{
    protected MistralShortAnswerGrader $mistral;
    protected RobloxPromotionService $promoter;
    protected \PDO $db;

    public function __construct(MistralShortAnswerGrader $mistral, RobloxPromotionService $promoter)
    {
        $this->mistral = $mistral;
        $this->promoter = $promoter;

        $dbFile = $GLOBALS['TEST_DB_PATH'] ?? (__DIR__ . '/../../storage/applications.db');
        $dbDir = dirname($dbFile);
        if (!is_dir($dbDir)) {
            @mkdir($dbDir, 0700, true);
        }

        try {
            $this->db = new \PDO('sqlite:' . $dbFile);
            $this->db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        } catch (\Throwable $e) {
            error_log('[SubmissionService] PDO initialization failed: ' . $e->getMessage());
            throw $e;
        }

        $this->initDb();
    }

    protected function initDb()
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS submissions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            app_id TEXT,
            applicant_id INTEGER,
            total_score REAL,
            max_score REAL,
            passed INTEGER,
            breakdown TEXT,
            created_at TEXT
        )");
    }

    public function handleSubmission(array $config, array $submission): array
    {
        $appId = $config['app']['id'] ?? 'unknown';
        $appIdFromRequest = $submission['app_id'] ?? $appId;
        $applicantId = (int)($submission['applicant_id'] ?? 0);
        $answers = $submission['answers'] ?? [];
        $creatorId = $config['app']['creator_id'] ?? ($submission['creator_id'] ?? 'creator_unknown');

        $breakdown = [];
        $totalScore = 0.0;
        $maxScoreSum = 0.0;

        $qmap = [];
        foreach ($config['questions'] ?? [] as $q) {
            if (!empty($q['id'])) $qmap[$q['id']] = $q;
        }

        foreach ($qmap as $qid => $q) {
            $qType = $q['type'] ?? '';
            $qPoints = isset($q['points']) ? (float)$q['points'] : 0.0;

            if ($qType === 'multiple_choice') {
                $maxScoreSum += $qPoints;
                $selected = $answers[$qid] ?? null;
                $correct = null;
                foreach ($q['options'] ?? [] as $opt) {
                    if (!empty($opt['correct'])) $correct = $opt['id'] ?? $correct;
                }
                $earned = ($selected !== null && $selected === $correct) ? $qPoints : 0.0;
                $totalScore += $earned;
                $breakdown[$qid] = ['type' => 'multiple_choice', 'earned' => $earned, 'max' => $qPoints];
            } elseif ($qType === 'checkboxes') {
                $maxScore = isset($q['max_score']) ? (float)$q['max_score'] : $qPoints;
                $maxScoreSum += $maxScore;
                $configScoring = $q['scoring'] ?? ['points_per_correct' => 1, 'penalty_per_incorrect' => 0];
                $perCorrect = (float)($configScoring['points_per_correct'] ?? 1);
                $penalty = (float)($configScoring['penalty_per_incorrect'] ?? 0);
                $selected = $answers[$qid] ?? [];
                $earned = 0.0;
                foreach ($q['options'] ?? [] as $opt) {
                    $isCorrect = !empty($opt['correct']);
                    $optId = $opt['id'] ?? null;
                    $isSelected = $optId !== null && is_array($selected) ? in_array($optId, $selected, true) : false;
                    if ($isSelected && $isCorrect) $earned += $perCorrect;
                    if ($isSelected && !$isCorrect) $earned -= $penalty;
                }
                if ($earned < 0) $earned = 0;
                if ($earned > $maxScore) $earned = $maxScore;
                $totalScore += $earned;
                $breakdown[$qid] = ['type' => 'checkboxes', 'earned' => $earned, 'max' => $maxScore];
            } elseif ($qType === 'short_answer') {
                $maxScore = isset($q['points']) ? (float)$q['points'] : 0.0;
                $maxScoreSum += $maxScore;
                $text = trim((string)($answers[$qid] ?? ''));
                if (isset($q['max_length']) && mb_strlen($text) > (int)$q['max_length']) {
                    $earned = 0.0;
                    $feedback = 'Answer too long';
                } else {
                    $grading = $this->mistral->gradeShortAnswer($q['text'] ?? '', $text, $q['grading_criteria'] ?? '', $maxScore);
                    $earned = (float)($grading['score'] ?? 0.0);
                    $feedback = $grading['feedback'] ?? '';
                }
                $totalScore += $earned;
                $breakdown[$qid] = ['type' => 'short_answer', 'earned' => $earned, 'max' => $maxScore, 'feedback' => $feedback];
            } else {
                if (isset($q['points'])) $maxScoreSum += (float)$q['points'];
                $breakdown[$qid] = ['type' => $qType, 'earned' => 0, 'max' => $qPoints];
            }
        }

        $passScore = (float)($config['app']['pass_score'] ?? 0.0);
        $percent = $maxScoreSum > 0 ? ($totalScore / $maxScoreSum) * 100.0 : 0.0;
        $passed = $percent >= $passScore;

        $stmt = $this->db->prepare("INSERT INTO submissions (app_id, applicant_id, total_score, max_score, passed, breakdown, created_at) VALUES (:app, :applicant, :total, :max, :passed, :break, :created)");
        $stmt->execute([
            ':app' => $appIdFromRequest,
            ':applicant' => $applicantId,
            ':total' => $totalScore,
            ':max' => $maxScoreSum,
            ':passed' => $passed ? 1 : 0,
            ':break' => json_encode($breakdown),
            ':created' => date('c'),
        ]);

        $promotionResult = null;
        if ($passed) {
            $membershipId = $submission['membership_id'] ?? ($GLOBALS['TEST_DEFAULT_MEMBERSHIP_ID'] ?? null);
            $groupId = $config['app']['group_id'] ?? ($GLOBALS['TEST_DEFAULT_GROUP_ID'] ?? null);
            $rolePath = $config['app']['target_role'] ?? null;

            if (empty($rolePath) && ($GLOBALS['TEST_DEFAULT_ROLE_ID'] ?? null)) {
                $defaultRoleId = intval($GLOBALS['TEST_DEFAULT_ROLE_ID'] ?? 0);
                if ($groupId) $rolePath = "groups/{$groupId}/roles/{$defaultRoleId}";
            }

            if ($groupId && $membershipId && $rolePath) {
                try {
                    $promotionResult = $this->promoter->promoteUsingCreatorKey($creatorId, (int)$groupId, (int)$membershipId, $rolePath);
                } catch (\Exception $e) {
                    error_log('Promotion failed: ' . $e->getMessage());
                    $promotionResult = ['error' => $e->getMessage()];
                }
            } else {
                $promotionResult = ['warning' => 'Promotion skipped: missing group/membership/role info'];
            }
        }

        $response = [
            'passed' => $passed,
            'total_score' => $totalScore,
            'max_score' => $maxScoreSum,
            'percent' => round($percent, 2),
            'breakdown' => $breakdown,
            'message' => $passed ? 'You passed!' : 'You did not pass this application.',
            'promotion' => $promotionResult,
        ];

        return $response;
    }
}