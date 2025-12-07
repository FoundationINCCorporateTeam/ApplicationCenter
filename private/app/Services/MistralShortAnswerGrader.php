<?php
namespace App\Services;

/**
 * MistralShortAnswerGrader — TEST MODE with explicit hard-coded Featherless API values
 *
 * WARNING: These test credentials are embedded for quick local testing only.
 * Do NOT commit this file with secrets to version control. Replace with environment or Vault in production.
 */

/* --- TEST / DEV hard-coded configuration (do NOT commit to VCS) --- */
$TEST_MISTRAL_API_KEY = 'rc_035de29202d1ed6dbf0151dc55a93a858391abc5563763aa08443d9034c06cae';
$TEST_BASE_URL = 'https://api.featherless.ai/v1'; // completion-style endpoint base
$TEST_MODEL_NAME = 'google/gemma-3-27b-it';
$TEST_MISTRAL_MODEL = $TEST_MODEL_NAME;
$TEST_MISTRAL_MAX_TOKENS = 300;
$TEST_MISTRAL_MAX_RETRIES = 1;
$TEST_MISTRAL_BACKOFF_SECONDS = 0.5;
$TEST_MISTRAL_TIMEOUT_SECONDS = 30;
/* ---------------------------------------------------------------- */

class MistralShortAnswerGrader
{
    protected string $apiKey;
    protected string $endpoint;
    protected string $model;
    protected string $logFile;

    public function __construct()
    {
        // Load API key and endpoint from environment or fallback globals
        $this->apiKey = getenv('API_KEY') ?: getenv('MISTRAL_API_KEY') ?: ($GLOBALS['TEST_MISTRAL_API_KEY'] ?? '');
        $base = getenv('BASE_URL') ?: getenv('MISTRAL_API_ENDPOINT') ?: ($GLOBALS['TEST_BASE_URL'] ?? '');
        $this->model = getenv('MODEL_NAME') ?: getenv('MISTRAL_MODEL') ?: ($GLOBALS['TEST_MISTRAL_MODEL'] ?? 'mistral-small');

        // Normalize endpoint
        if (!empty($base)) {
            if (stripos($base, 'completions') !== false || stripos($base, 'chat') !== false) {
                $this->endpoint = rtrim($base, '/');
            } else {
                $this->endpoint = rtrim($base, '/') . '/completions';
            }
        } else {
            $this->endpoint = getenv('MISTRAL_API_ENDPOINT') ?: 'https://api.mistral.example/v1/chat/completions';
        }

        // Log file path
        $this->logFile = __DIR__ . '/mistral_responses.log';

        if (empty($this->apiKey)) {
            error_log('[MistralShortAnswerGrader] Mistral API key not set; grading will fallback to provisional scoring.');
        }
    }

    /**
     * Grades a short answer and always returns a structured array:
     * ['score'=>float, 'max_score'=>float, 'feedback'=>string, 'model_response'=>string|null]
     * Logs raw model response to mistral_responses.log
     */
    public function gradeShortAnswer(string $questionText, string $answerText, string $criteria, float $maxScore): array
    {
        $prompt = $this->buildPrompt($questionText, $answerText, $criteria, $maxScore);

        $payload = [
            'model' => $this->model,
            'prompt' => $prompt,
            'max_tokens' => intval(getenv('MISTRAL_MAX_TOKENS') ?: ($GLOBALS['TEST_MISTRAL_MAX_TOKENS'] ?? 300)),
        ];

        $attempt = 0;
        $maxAttempts = intval(getenv('MISTRAL_MAX_RETRIES') ?: ($GLOBALS['TEST_MISTRAL_MAX_RETRIES'] ?? 3));
        $wait = floatval(getenv('MISTRAL_BACKOFF_SECONDS') ?: ($GLOBALS['TEST_MISTRAL_BACKOFF_SECONDS'] ?? 0.5));

        $lastResp = null;
        $lastErr = null;

        while ($attempt < $maxAttempts) {
            $attempt++;
            try {
                $ch = curl_init($this->endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

                $headers = ['Content-Type: application/json'];
                if (!empty($this->apiKey)) {
                    $headers[] = 'Authorization: Bearer ' . $this->apiKey;
                }
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
                curl_setopt($ch, CURLOPT_TIMEOUT, intval(getenv('MISTRAL_TIMEOUT_SECONDS') ?: ($GLOBALS['TEST_MISTRAL_TIMEOUT_SECONDS'] ?? 10)));

                $resp = curl_exec($ch);
                $err = curl_error($ch);
                $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                $lastResp = is_string($resp) ? $resp : null;
                $lastErr = $err !== '' ? $err : null;

                // --- Log the raw model response ---
                if ($lastResp !== null) {
                    $this->logModelResponse($lastResp, $questionText, $answerText);
                }

                if ($err) {
                    throw new \Exception('Curl error: ' . $err);
                }

                // Handle client errors (4xx) with provisional score
                if ($code >= 400 && $code < 500) {
                    $reason = $code === 401
                        ? 'Grading service unauthorized (401); provisional score assigned.'
                        : 'Grading service returned error; provisional score assigned.';
                    return [
                        'score' => round($maxScore / 2, 2),
                        'max_score' => $maxScore,
                        'feedback' => $reason,
                        'model_response' => $lastResp,
                    ];
                }

                if ($code >= 500) {
                    throw new \Exception('Server error: ' . $code);
                }

                // Try to parse JSON response from model
                $jsonCandidate = $this->extractJson((string)$resp);
                if ($jsonCandidate !== null) {
                    $decoded = json_decode($jsonCandidate, true);
                    if (is_array($decoded) && isset($decoded['score'])) {
                        return [
                            'score' => max(0, min($maxScore, (float)$decoded['score'])),
                            'max_score' => $maxScore,
                            'feedback' => isset($decoded['feedback']) ? (string)$decoded['feedback'] : '',
                            'model_response' => $lastResp,
                        ];
                    }
                }

                // Fallback: attempt parsing from free text
                $fallback = $this->attemptParseFromText((string)$resp, $maxScore);
                if ($fallback !== null) {
                    $fallback['model_response'] = $lastResp;
                    return $fallback;
                }

                // Unparseable response: provisional score + raw model response
                return [
                    'score' => round($maxScore / 2, 2),
                    'max_score' => $maxScore,
                    'feedback' => 'Model response returned (not parseable) — inspect model_response.',
                    'model_response' => $lastResp,
                ];

            } catch (\Exception $e) {
                $lastErr = $e->getMessage();
                error_log("[MistralShortAnswerGrader] grading attempt {$attempt} failed: " . $e->getMessage());

                if ($attempt >= $maxAttempts) {
                    $feedback = !empty($lastErr) && empty($lastResp)
                        ? 'Grading service error: ' . $lastErr . ' ; provisional score assigned.'
                        : 'Grading service unavailable; provisional score assigned.';
                    return [
                        'score' => round($maxScore / 2, 2),
                        'max_score' => $maxScore,
                        'feedback' => $feedback,
                        'model_response' => $lastResp,
                    ];
                }

                // Exponential backoff
                usleep((int)($wait * 1e6));
                $wait *= 2;
            }
        }

        // Fallback: unexpected flow
        return [
            'score' => round($maxScore / 2, 2),
            'max_score' => $maxScore,
            'feedback' => 'Unable to grade (unexpected flow).',
            'model_response' => $lastResp,
        ];
    }

    protected function buildPrompt(string $q, string $a, string $criteria, float $maxScore): string
    {
        return "You are an unbiased grader for Roblox group applications. Grade according to criteria.\n\nQuestion:\n{$q}\n\nApplicant answer:\n{$a}\n\nGrading criteria:\n{$criteria}\n\nReturn ONLY a JSON object: {\"score\": <number>, \"max_score\": {$maxScore}, \"feedback\": \"Short feedback\" }.";
    }

    protected function extractJson(string $text): ?string
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) return null;
        $candidate = substr($text, $start, $end - $start + 1);
        return json_decode($candidate, true) !== null ? $candidate : null;
    }

    protected function attemptParseFromText(string $text, float $maxScore): ?array
    {
        if (preg_match('/"score"\s*:\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $m)) {
            $score = max(0, min($maxScore, (float)$m[1]));
            return ['score' => $score, 'max_score' => $maxScore, 'feedback' => trim($text)];
        }
        if (preg_match('/([0-9]+(?:\.[0-9]+)?)\s*(?:\/|out of)\s*([0-9]+(?:\.[0-9]+)?)/i', $text, $m)) {
            $num = (float)$m[1];
            $den = (float)$m[2];
            if ($den <= 0) $den = $maxScore;
            $scaled = max(0, min($maxScore, ($num / $den) * $maxScore));
            return ['score' => round($scaled, 2), 'max_score' => $maxScore, 'feedback' => trim($text)];
        }
        return null;
    }

    /**
     * Log the raw model response to a .log file
     */
    protected function logModelResponse(string $response, string $question, string $answer): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] QUESTION: $question\nANSWER: $answer\nMODEL_RESPONSE: $response\n-------------------------\n";
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

