<?php
/**
 * Submission Controller
 * 
 * Handles application submissions and grading
 */
class SubmissionController {
    const MAX_SHORT_ANSWER_QUESTIONS = 3;

    private $dataDir;
    private $grader;
    private $promotionService;

    public function __construct() {
        $this->dataDir = __DIR__ . '/../data/submissions';
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }

        $this->grader = new FeatherlessGrader();
        $this->promotionService = new PromotionService();
    }

    /**
     * Submit and grade an application
     */
    public function submit($data) {
        // 1. Normalize Roblox field names
        if (isset($data['responses']) && !isset($data['answers'])) {
            $normalized = [];
            foreach ($data['responses'] as $qId => $payload) {
                if (isset($payload['answer'])) {
                    $normalized[$qId] = $payload['answer'];
                } elseif (isset($payload['selected']) && is_array($payload['selected'])) {
                    $normalized[$qId] = $payload['selected'];
                } else {
                    $normalized[$qId] = $payload;
                }
            }
            $data['answers'] = $normalized;
        }

        // 2. Validate required fields
        $validation = validateRequired($data, ['app_id', 'user_id', 'answers']);
        if ($validation !== true) {
            return ['error' => $validation];
        }

        // 3. Sanitize input
        $appId   = sanitize($data['app_id']);
        $userId  = intval($data['user_id']);
        $answers = $data['answers'];

        // 4. Load application config
        $appController = new AppController();
        $appResult = $appController->loadApp($appId);
        if (isset($appResult['error'])) {
            return ['error' => 'Application not found'];
        }
        $config = $appResult['data'];

        // 5. Validate all questions have answers
        foreach (($config['questions'] ?? []) as $question) {
            $qId = $question['id'];
            if (!isset($answers[$qId]) || $answers[$qId] === '' || (is_array($answers[$qId]) && count($answers[$qId]) === 0)) {
                return ['error' => "Missing answer for question {$qId}"];
            }
        }

        // 6. Grade submission
        $gradingResult = $this->gradeSubmission($config, $answers);
        $totalScore = 0;
        $maxScore   = 0;
        foreach ($gradingResult['results'] as $result) {
            $totalScore += $result['score'];
            $maxScore   += $result['max_score'];
        }
        $percentage = $maxScore > 0 ? ($totalScore / $maxScore) * 100 : 0;
        $passScore  = $config['app']['pass_score'] ?? 70;
        $passed     = $percentage >= $passScore;

        // 7. Build submission record
        $submissionId = generateId();
        $submission = [
            'id'           => $submissionId,
            'app_id'       => $appId,
            'user_id'      => $userId,
            'answers'      => $answers,
            'results'      => $gradingResult['results'],
            'total_score'  => $totalScore,
            'max_score'    => $maxScore,
            'percentage'   => round($percentage, 2),
            'passed'       => $passed,
            'promoted'     => false,
            'submitted_at' => date('c')
        ];

        if ($passed) {
    $groupId    = $config['app']['group_id'] ?? null;
    $targetRole = $config['app']['target_role'] ?? null;

    if (!$groupId || !$targetRole) {
        error_log("[Promotion Debug] Promotion skipped: Group ID or target role missing");
    } else {
        // Extract the rank number from the target_role string
        preg_match('/roles\/(\d+)$/', $targetRole, $matches);
        $targetRank = isset($matches[1]) ? (int)$matches[1] : null;

        if (!$targetRank) {
            error_log("[Promotion Debug] Promotion skipped: Could not parse target rank from target_role");
        } else {
            try {
                // Always attempt promotion
                $promotionResult = $this->promotionService->promoteUser($groupId, $userId, $targetRank);

                // Debug output
                error_log("[Promotion Debug] User {$userId} in group {$groupId} attempting rank {$targetRank}");
                error_log("[Promotion Debug] API result: " . json_encode($promotionResult));

                // Handle Roblox API returning "Cannot change the role for the same user"
                if (!$promotionResult['success'] && strpos($promotionResult['message'], 'Cannot change the role') !== false) {
                    $promotionResult['success'] = true;
                    $promotionResult['message'] = 'User already has the target role';
                }
            } catch (Exception $e) {
                $promotionResult = [
                    'success' => false,
                    'message' => 'Promotion failed: ' . $e->getMessage()
                ];
                error_log("[Promotion Debug] Exception: " . $e->getMessage());
            }

            $submission['promoted']         = $promotionResult['success'];
            $submission['promotion_message'] = $promotionResult['message'];

            // Log final promotion status
            error_log("[Promotion Debug] Final status: " . json_encode([
                'promoted' => $submission['promoted'],
                'message'  => $submission['promotion_message']
            ]));
        }
    }
}
        // 9. Save submission to disk
        $filePath = "{$this->dataDir}/{$submissionId}.json";
        saveJson($filePath, $submission);

        // 10. Return full response
        return [
            'success'       => true,
            'submission_id' => $submissionId,
            'passed'        => $passed,
            'score'         => $totalScore,
            'max_score'     => $maxScore,
            'percentage'    => round($percentage, 2),
            'results'       => $gradingResult['results'],
            'promoted'      => $submission['promoted']
        ];
    }

    /**
     * Grade a submission
     */
    private function gradeSubmission($config, $answers) {
        $results = [];
        $questions = $config['questions'] ?? [];
        $shortAnswerCount = 0;

        foreach ($questions as $question) {
            $qId = $question['id'];
            $type = $question['type'];
            $answer = $answers[$qId] ?? null;

            if ($type === 'multiple_choice') {
                $results[$qId] = $this->gradeMultipleChoice($question, $answer);
            } elseif ($type === 'checkboxes') {
                $results[$qId] = $this->gradeCheckboxes($question, $answer);
            } elseif ($type === 'short_answer') {
                $shortAnswerCount++;
                if ($shortAnswerCount > self::MAX_SHORT_ANSWER_QUESTIONS) {
                    $results[$qId] = [
                        'score' => 0,
                        'max_score' => $question['points'] ?? 0,
                        'feedback' => 'Too many short answer questions (max ' . self::MAX_SHORT_ANSWER_QUESTIONS . ')'
                    ];
                } else {
                    $results[$qId] = $this->gradeShortAnswer($question, $answer);
                }
            }
        }

        return ['results' => $results];
    }
    /**
     * Grade a multiple choice question
     */
    private function gradeMultipleChoice($question, $answer) {
        $points = $question['points'] ?? 10;
        $options = $question['options'] ?? [];
        
        if (!$answer) {
            return [
                'score' => 0,
                'max_score' => $points,
                'feedback' => 'No answer provided'
            ];
        }
        
        foreach ($options as $option) {
            if ($option['id'] === $answer && ($option['correct'] ?? false)) {
                return [
                    'score' => $points,
                    'max_score' => $points,
                    'feedback' => 'Correct!'
                ];
            }
        }
        
        return [
            'score' => 0,
            'max_score' => $points,
            'feedback' => 'Incorrect answer'
        ];
    }
    
    /**
     * Grade a checkboxes question
     */
    private function gradeCheckboxes($question, $answers) {
        $maxScore = $question['max_score'] ?? $question['points'] ?? 20;
        $options = $question['options'] ?? [];
        $scoring = $question['scoring'] ?? [
            'points_per_correct' => 5,
            'penalty_per_incorrect' => 1
        ];
        
        if (!is_array($answers)) {
            $answers = [];
        }
        
        $score = 0;
        $correctAnswers = [];
        
        foreach ($options as $option) {
            if ($option['correct'] ?? false) {
                $correctAnswers[] = $option['id'];
            }
        }
        
        // Award points for correct selections
        foreach ($answers as $answerId) {
            if (in_array($answerId, $correctAnswers)) {
                $score += $scoring['points_per_correct'];
            } else {
                $score -= $scoring['penalty_per_incorrect'];
            }
        }
        
        // Penalize for missed correct answers
        foreach ($correctAnswers as $correctId) {
            if (!in_array($correctId, $answers)) {
                // Already penalized by not awarding points
            }
        }
        
        $score = max(0, min($maxScore, $score));
        
        return [
            'score' => $score,
            'max_score' => $maxScore,
            'feedback' => "Selected " . count($answers) . " options, " . 
                         count(array_intersect($answers, $correctAnswers)) . " correct"
        ];
    }
    
    /**
     * Grade a short answer question using AI
     */
    private function gradeShortAnswer($question, $answer) {
        $maxScore = $question['points'] ?? 20;
        
        if (!$answer || trim($answer) === '') {
            return [
                'score' => 0,
                'max_score' => $maxScore,
                'feedback' => 'No answer provided'
            ];
        }
        
        // Enforce max length
        $maxLength = $question['max_length'] ?? 300;
        if (strlen($answer) > $maxLength) {
            return [
                'score' => 0,
                'max_score' => $maxScore,
                'feedback' => "Answer exceeds maximum length of {$maxLength} characters"
            ];
        }
        
        try {
            $result = $this->grader->gradeShortAnswer(
                $question['text'],
                $answer,
                $question['grading_criteria'] ?? 'Grade based on relevance and quality',
                $maxScore
            );
            
            return $result;
        } catch (Exception $e) {
            return [
                'score' => 0,
                'max_score' => $maxScore,
                'feedback' => 'Grading failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get a submission by ID
     */
    public function getSubmission($id) {
        $id = sanitize($id);
        $filePath = "{$this->dataDir}/{$id}.json";
        
        $data = loadJson($filePath);
        
        if (!$data) {
            return ['error' => 'Submission not found'];
        }
        
        return [
            'success' => true,
            'submission' => $data
        ];
    }
    
    /**
     * List submissions for an application
     */
    public function listSubmissions($appId = null) {
        $files = glob("{$this->dataDir}/*.json");
        $submissions = [];
        
        foreach ($files as $file) {
            $data = loadJson($file);
            
            if ($data) {
                if ($appId === null || $data['app_id'] === $appId) {
                    $submissions[] = $data;
                }
            }
        }
        
        // Sort by submitted_at descending
        usort($submissions, function($a, $b) {
            return strtotime($b['submitted_at']) - strtotime($a['submitted_at']);
        });
        
        return [
            'success' => true,
            'submissions' => $submissions
        ];
    }
}