<?php
/**
 * Featherless AI Grader
 * 
 * Uses Gemma-3-27B-IT through Featherless AI API to grade short answer questions
 */
class FeatherlessGrader {
    // API Configuration constants
    const DEFAULT_MAX_TOKENS = 1000;
    const DEFAULT_TEMPERATURE = 0.7;
    
    private $apiKey;
    private $baseUrl;
    private $model;
    
    public function __construct() {
        $this->apiKey = Env::get('FEATHERLESS_API_KEY');
        $this->baseUrl = Env::get('FEATHERLESS_BASE_URL', 'https://api.featherless.ai/v1');
        $this->model = Env::get('FEATHERLESS_MODEL', 'google/gemma-3-27b-it');
        
        if (!$this->apiKey) {
            throw new Exception('FEATHERLESS_API_KEY not configured');
        }
    }
    
    /**
     * Grade a short answer question
     * 
     * @param string $questionText The question text
     * @param string $answerText The applicant's answer
     * @param string $gradingCriteria Grading criteria
     * @param int $maxScore Maximum possible score
     * @return array Grading result with score, max_score, and feedback
     */
    public function gradeShortAnswer($questionText, $answerText, $gradingCriteria, $maxScore) {
        $prompt = $this->buildGradingPrompt($questionText, $answerText, $gradingCriteria, $maxScore);
        
        $response = $this->callApi($prompt);
        
        return $this->parseGradingResponse($response, $maxScore);
    }
    
    /**
     * Build grading prompt
     */
    private function buildGradingPrompt($questionText, $answerText, $gradingCriteria, $maxScore) {
        return <<<PROMPT
You are an unbiased grader for a Roblox group application system.

Question: {$questionText}
Applicant Answer: {$answerText}
Grading Criteria: {$gradingCriteria}
Maximum Score: {$maxScore}

Please grade this answer fairly and objectively. Consider:
- Relevance to the question
- Quality and depth of the response
- Adherence to the grading criteria
- Completeness of the answer

Respond ONLY in valid JSON format with this exact structure:
{
  "score": [number between 0 and {$maxScore}],
  "max_score": {$maxScore},
  "feedback": "[brief explanation of the score]"
}

Do not include any text before or after the JSON object.
PROMPT;
    }
    
    /**
     * Call Featherless AI API
     */
    private function callApi($prompt) {
        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'max_tokens' => Env::get('FEATHERLESS_MAX_TOKENS', self::DEFAULT_MAX_TOKENS),
            'temperature' => floatval(Env::get('FEATHERLESS_TEMPERATURE', self::DEFAULT_TEMPERATURE))
        ];
        
        $ch = curl_init($this->baseUrl . '/completions');
        
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        curl_close($ch);
        
        if ($error) {
            throw new Exception("API request failed: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("API returned HTTP $httpCode: $response");
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['choices'][0]['text'])) {
            throw new Exception("Unexpected API response format");
        }
        
        return $data['choices'][0]['text'];
    }
    
    /**
     * Parse grading response
     */
    private function parseGradingResponse($responseText, $maxScore) {
        // Try to extract JSON from the response
        $responseText = trim($responseText);
        
        // Look for JSON object in the response
        if (preg_match('/\{[^}]*"score"[^}]*\}/s', $responseText, $matches)) {
            $jsonText = $matches[0];
            $result = json_decode($jsonText, true);
            
            if ($result && isset($result['score'])) {
                // Ensure score is within bounds
                $score = max(0, min($maxScore, floatval($result['score'])));
                
                return [
                    'score' => $score,
                    'max_score' => $maxScore,
                    'feedback' => $result['feedback'] ?? 'No feedback provided'
                ];
            }
        }
        
        // Fallback: try to parse the entire response as JSON
        $result = json_decode($responseText, true);
        if ($result && isset($result['score'])) {
            $score = max(0, min($maxScore, floatval($result['score'])));
            
            return [
                'score' => $score,
                'max_score' => $maxScore,
                'feedback' => $result['feedback'] ?? 'No feedback provided'
            ];
        }
        
        // If all else fails, return 0 score with error message
        return [
            'score' => 0,
            'max_score' => $maxScore,
            'feedback' => 'Unable to parse grading response. Please review manually.'
        ];
    }
}
