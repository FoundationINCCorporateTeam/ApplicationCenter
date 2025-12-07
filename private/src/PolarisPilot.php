<?php
/**
 * PolarisPilot - Server-side wrapper for Featherless AI (meta-llama/Llama-3.1-8B)
 *
 * Usage:
 *   - Ensure Env::load(__DIR__ . '/../private/.env') has been called (index.php does this).
 *   - The FEATHERLESS_API_KEY environment variable must be set in /private/.env.
 *   - Call PolarisPilot::generateForm($params) where $params is an associative array
 *     containing keys such as: name, description, group_id, rank, questions, vibe,
 *     primary_color, secondary_color, instructions (optional free-form prompt).
 *
 * Returns:
 *   - On success: ['success' => true, 'form' => <array>, 'raw' => <string>]
 *   - On error:   ['error' => '<message>']
 *
 * Notes:
 *   - The class enforces the model response is valid JSON (extracts JSON substring if needed).
 *   - It does light post-processing/normalization of questions/options to match the builder format.
 *   - The server-side API key is never exposed to the client.
 */

class PolarisPilot
{
    // Featherless predict endpoint
    private const ENDPOINT = 'https://api.featherless.ai/v1';
    private const MODEL = 'mistralai/Mistral-7B-Instruct-v0.2';

    /**
     * Read API key from environment.
     * @return string|null
     */
    private static function getApiKey(): ?string
    {
        $key = getenv('FEATHERLESS_API_KEY');
        return $key && is_string($key) ? trim($key) : null;
    }

    /**
     * Low-level call to Featherless predict endpoint.
     * Returns ['ok'=>true,'text'=>... ] or ['error'=>...]
     *
     * @param string $prompt
     * @param int $max_tokens
     * @param float $temperature
     * @return array
     */
    private static function callFeatherless(string $prompt, int $max_tokens = 1200, float $temperature = 0.1): array
    {
        $apiKey = self::getApiKey();
        if (!$apiKey) {
            return ['error' => 'FEATHERLESS_API_KEY not configured on server'];
        }

        // Use the completions path required by Featherless
        $endpoint = rtrim(self::ENDPOINT, '/') . '/completions';

        $payload = [
            'model' => self::MODEL,
            'prompt' => $prompt,
            'max_tokens' => $max_tokens,
            'temperature' => $temperature
        ];

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);

        $resp = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErr) {
            return ['error' => 'Featherless request failed: ' . $curlErr];
        }
        if ($httpCode >= 400) {
            return ['error' => "Featherless returned HTTP {$httpCode}: {$resp}"];
        }

        // Try to decode the JSON response
        $decoded = json_decode($resp, true);
        $text = '';

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            // Common completions shapes:
            // { "choices": [ { "text": "..." } ] }
            // or Featherless variations { "choices": [ { "message": { "content": "..." } } ] }
            if (!empty($decoded['choices']) && is_array($decoded['choices'])) {
                $choice = $decoded['choices'][0];
                if (isset($choice['text']) && is_string($choice['text'])) {
                    $text = $choice['text'];
                } elseif (isset($choice['message']['content']) && is_string($choice['message']['content'])) {
                    $text = $choice['message']['content'];
                } elseif (is_string($choice)) {
                    // sometimes choice might be string
                    $text = $choice;
                } else {
                    // fallback: stringify the first choice
                    $text = is_array($choice) ? json_encode($choice) : strval($choice);
                }
            } elseif (isset($decoded['output'])) {
                // older/predict-style responses
                $out = $decoded['output'];
                if (is_string($out)) {
                    $text = $out;
                } elseif (is_array($out)) {
                    // join any string parts
                    $parts = array_map(function ($p) {
                        return is_string($p) ? $p : (is_array($p) ? json_encode($p) : strval($p));
                    }, $out);
                    $text = implode('', $parts);
                } else {
                    $text = json_encode($out);
                }
            } elseif (isset($decoded['result'])) {
                $text = is_string($decoded['result']) ? $decoded['result'] : json_encode($decoded['result']);
            } elseif (isset($decoded['data']) && is_string($decoded['data'])) {
                $text = $decoded['data'];
            } else {
                // fallback to stringified response
                $text = json_encode($decoded);
            }
        } else {
            // Response wasn't valid JSON — treat raw text as model output
            $text = $resp;
        }

        // Trim and return raw model text as well
        return ['ok' => true, 'text' => trim((string)$text), 'raw' => $resp];
    }

    /**
     * Attempt to extract the first JSON object substring from arbitrary text.
     * Returns decoded array on success, null on failure.
     *
     * @param string $text
     * @return array|null
     */
    private static function extractJson(string $text): ?array
    {
        // If the entire text is valid JSON, decode directly.
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Attempt to find a JSON object substring by scanning for balanced braces.
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }

        $len = strlen($text);
        $depth = 0;
        $inString = false;
        for ($i = $start; $i < $len; $i++) {
            $ch = $text[$i];
            // handle escape in string
            if ($ch === '"' && ($i === 0 || $text[$i - 1] !== '\\')) {
                $inString = !$inString;
            }
            if ($inString) {
                continue;
            }
            if ($ch === '{') {
                $depth++;
            } elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) {
                    $candidate = substr($text, $start, $i - $start + 1);
                    $try = json_decode($candidate, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        return $try;
                    }
                    // otherwise continue searching for next balanced closing brace (rare)
                }
            }
        }

        return null;
    }

    /**
     * Normalize and validate the generated form structure minimally:
     * - ensures app exists, questions is array
     * - ensures question ids/options are strings and options have boolean correct
     *
     * Returns normalized form array or array('error'=>..) on failure.
     *
     * @param array $form
     * @param array $fallbackColors ['primary','secondary']
     * @param int|null $fallbackGroupId
     * @param string|null $fallbackRank
     * @return array
     */
    private static function normalizeForm(array $form, array $fallbackColors = ['#ff4b6e', '#1f2933'], ?int $fallbackGroupId = null, ?string $fallbackRank = null): array
    {
        // Validate app
        if (!isset($form['app']) || !is_array($form['app'])) {
            return ['error' => 'Generated JSON missing "app" object'];
        }
        if (!isset($form['questions']) || !is_array($form['questions'])) {
            return ['error' => 'Generated JSON missing "questions" array'];
        }

        $app = $form['app'];

        // Ensure group_id is set (use fallback if model omitted)
        if (empty($app['group_id']) && $fallbackGroupId) {
            $app['group_id'] = intval($fallbackGroupId);
        } else {
            $app['group_id'] = isset($app['group_id']) ? intval($app['group_id']) : null;
        }

        // Normalize target_role: if model returned only rank or numeric, build full path
        $target = $app['target_role'] ?? '';
        if ($target === '' && $fallbackGroupId && $fallbackRank !== null && $fallbackRank !== '') {
            $target = "groups/{$fallbackGroupId}/roles/{$fallbackRank}";
        } elseif (preg_match('/^\d+$/', (string)$target) && $app['group_id']) {
            $target = "groups/{$app['group_id']}/roles/{$target}";
        }
        $app['target_role'] = $target;

        // Style fallback
        $style = $form['style'] ?? [];
        $style['primary_color'] = $style['primary_color'] ?? $fallbackColors[0];
        $style['secondary_color'] = $style['secondary_color'] ?? $fallbackColors[1];

        // Normalize questions and options
        $questions = [];
        foreach ($form['questions'] as $qi => $q) {
            if (!is_array($q)) continue;
            $qid = isset($q['id']) ? (string)$q['id'] : ('q' . round(microtime(true) * 1000) . $qi);
            $type = isset($q['type']) ? (string)$q['type'] : 'short_answer';
            $text = isset($q['text']) ? (string)$q['text'] : ('Question ' . ($qi + 1));
            $points = isset($q['points']) ? intval($q['points']) : 5;
            $options = [];
            if (isset($q['options']) && is_array($q['options'])) {
                foreach ($q['options'] as $oi => $opt) {
                    if (!is_array($opt)) continue;
                    $oid = isset($opt['id']) ? (string)$opt['id'] : ('opt' . $oi);
                    $otext = isset($opt['text']) ? (string)$opt['text'] : ('Option ' . ($oi + 1));
                    $ocorrect = isset($opt['correct']) ? (bool)$opt['correct'] : false;
                    $options[] = ['id' => $oid, 'text' => $otext, 'correct' => $ocorrect];
                }
            }

            // For short_answer, ensure options is empty array
            if ($type === 'short_answer') {
                $options = [];
            }

            $questions[] = [
                'id' => $qid,
                'type' => $type,
                'text' => $text,
                'points' => $points,
                'options' => $options
            ];
        }

        // Basic validation: at least 1 question
        if (count($questions) === 0) {
            return ['error' => 'Generated form has no valid questions'];
        }

        return [
            'success' => true,
            'form' => [
                'app' => $app,
                'style' => $style,
                'questions' => $questions
            ]
        ];
    }

    /**
     * Public: generate a form using provided parameters.
     *
     * Expected $params keys:
     *   - name (string)
     *   - description (string)
     *   - group_id (int|string)
     *   - rank (string|int)    // role rank (e.g. 124)
     *   - questions (int)      // number of questions to generate
     *   - vibe (string)        // tone
     *   - primary_color (string) hex
     *   - secondary_color (string) hex
     *   - instructions (string) optional free-form prompt to guide generation
     *
     * Returns ['success'=>true,'form'=><array>,'raw'=><string>] or ['error'=>...]
     *
     * @param array $params
     * @return array
     */
    public static function generateForm(array $params): array
    {
        // sanitize inputs
        $name = isset($params['name']) ? trim((string)$params['name']) : 'Generated Application';
        $description = isset($params['description']) ? trim((string)$params['description']) : '';
        $group_id = isset($params['group_id']) ? intval($params['group_id']) : 0;
        $rank = isset($params['rank']) ? trim((string)$params['rank']) : '';
        $questionsCount = isset($params['questions']) ? max(1, intval($params['questions'])) : 6;
        $vibe = isset($params['vibe']) ? trim((string)$params['vibe']) : 'professional and friendly';
        $primary = isset($params['primary_color']) ? trim((string)$params['primary_color']) : '#ff4b6e';
        $secondary = isset($params['secondary_color']) ? trim((string)$params['secondary_color']) : '#1f2933';
        $instructions = isset($params['instructions']) ? trim((string)$params['instructions']) : '';

        // Build the prompt: instruct the model to output strictly VALID JSON following schema.
        $promptParts = [];

        $promptParts[] = "You are Polaris Pilot, an assistant that generates Roblox application forms.";
        $promptParts[] = "Output ONLY a single JSON object and NOTHING else. The JSON must match exactly this schema (do not add or remove keys):";
        $promptParts[] = json_encode([
            'app' => [
                'name' => '<string>',
                'description' => '<string>',
                'group_id' => '<integer>',
                'target_role' => 'groups/{group_id}/roles/{rank}'
            ],
            'style' => ['primary_color' => '#rrggbb', 'secondary_color' => '#rrggbb'],
            'questions' => [
                [
                    'id' => 'q<unixms>',
                    'type' => 'multiple_choice|checkboxes|short_answer',
                    'text' => '<string>',
                    'points' => '<integer>',
                    'options' => [
                        ['id' => 'opt0', 'text' => '<string>', 'correct' => true]
                    ]
                ]
            ]
        ], JSON_PRETTY_PRINT);

        $promptParts[] = "CONSTRAINTS:";
        $promptParts[] = "- Provide exactly {$questionsCount} questions in the 'questions' array.";
        $promptParts[] = "- For multiple_choice: include at least 2 options and exactly one option with correct:true.";
        $promptParts[] = "- For checkboxes: include at least 2 options and at least one option with correct:true (possibly multiple).";
        $promptParts[] = "- For short_answer: set options to an empty array [].";
        $promptParts[] = "- Use integer points in range 1-20 appropriate to question difficulty.";
        $promptParts[] = "- Use question ids starting with 'q' followed by current Unix milliseconds (or similar unique string). Option ids may be 'opt0','opt1', etc.";
        $promptParts[] = "- Set app.group_id to {$group_id}. Set app.target_role to groups/{$group_id}/roles/{$rank} (if rank is provided). If rank is empty, place '{RANK}' as placeholder in the string.";
        $promptParts[] = "- Use the provided colors for style.primary_color and style.secondary_color.";
        $promptParts[] = "- Do NOT include commentary, markdown, or any extra text — only the JSON object.";

        $promptParts[] = "USER CONTEXT:";
        $promptParts[] = "name: " . $name;
        $promptParts[] = "description: " . $description;
        $promptParts[] = "vibe/tone: " . $vibe;
        $promptParts[] = "If additional instructions are provided, follow them carefully. Instructions: " . ($instructions ?: '[none]');

        $prompt = implode("\n\n", $promptParts);

        // Call the model
        $call = self::callFeatherless($prompt, 1800, 0.05);
        if (isset($call['error'])) {
            return ['error' => $call['error']];
        }

        $raw = $call['text'] ?? '';
        if (!$raw) {
            return ['error' => 'Empty response from model'];
        }

        // Try to decode or extract JSON
        $decoded = self::extractJson($raw);
        if ($decoded === null) {
            return ['error' => 'Model output could not be parsed as JSON. Raw output: ' . substr($raw, 0, 4000)];
        }

        // If model didn't set app.group_id or target_role properly, fill from params
        if (!isset($decoded['app'])) {
            return ['error' => 'Generated JSON missing "app" key'];
        }
        if (!isset($decoded['questions']) || !is_array($decoded['questions'])) {
            return ['error' => 'Generated JSON missing "questions" array'];
        }

        // Normalize and validate
        $norm = self::normalizeForm($decoded, [$primary, $secondary], $group_id > 0 ? $group_id : null, $rank !== '' ? $rank : null);
        if (isset($norm['error'])) {
            return ['error' => $norm['error'] . ' Raw output: ' . substr($raw, 0, 2000)];
        }

        // Return success + form + raw text
        return [
            'success' => true,
            'form' => $norm['form'],
            'raw' => $raw
        ];
    }
}