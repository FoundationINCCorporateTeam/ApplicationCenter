<?php
namespace App\Services;

/**
 * AstParser
 *
 * A simple parser + serializer for the .astappcnt DSL.
 * The DSL is block-based (APP, STYLE, QUESTION "id" TYPE "type" {...})
 *
 * This parser is intentionally forgiving and simple so it can be implemented in PHP and Luau.
 * It converts AST text -> PHP array (and has a simple serializer array -> AST text).
 *
 * Notes:
 * - For production, consider switching to JSON or a stricter grammar parser.
 */
class AstParser
{
    /**
     * Parse DSL text into array structure.
     *
     * @param string $text
     * @return array
     */
    public function parse(string $text): array
    {
        $result = [
            'app' => [],
            'style' => [],
            'questions' => [],
        ];

        // Normalize line endings
        $text = str_replace("\r\n", "\n", $text);

        // Use regex to extract APP block, STYLE block, and QUESTION blocks
        if (preg_match('/APP\s*\{([^}]*)\}/s', $text, $m)) {
            $result['app'] = $this->parseKeyValues($m[1]);
            // cast numeric fields
            if (isset($result['app']['group_id'])) {
                $result['app']['group_id'] = (int)$result['app']['group_id'];
            }
            if (isset($result['app']['pass_score'])) {
                $result['app']['pass_score'] = (float)$result['app']['pass_score'];
            }
        }

        if (preg_match('/STYLE\s*\{([^}]*)\}/s', $text, $m)) {
            $result['style'] = $this->parseKeyValues($m[1]);
        }

        // QUESTIONS
        if (preg_match_all('/QUESTION\s*"([^"]+)"\s*TYPE\s*"([^"]+)"\s*\{([^}]*)\}/s', $text, $qMatches, PREG_SET_ORDER)) {
            foreach ($qMatches as $qm) {
                $id = $qm[1];
                $type = $qm[2];
                $body = $qm[3];
                $q = $this->parseKeyValues($body);

                $q['id'] = $id;
                $q['type'] = $type;

                // parse options array if present
                if (preg_match('/options\s*:\s*\[([^\]]*)\]/s', $body, $om)) {
                    $optsText = $om[1];
                    $q['options'] = $this->parseOptions($optsText);
                }

                // parse scoring block if present
                if (preg_match('/scoring\s*:\s*\{([^}]*)\}/s', $body, $sm)) {
                    $q['scoring'] = $this->parseKeyValues($sm[1]);
                }

                // Ensure numeric casts
                if (isset($q['points'])) $q['points'] = (float)$q['points'];
                if (isset($q['max_score'])) $q['max_score'] = (float)$q['max_score'];
                if (isset($q['max_length'])) $q['max_length'] = (int)$q['max_length'];

                $result['questions'][] = $q;
            }
        }

        return $result;
    }

    /**
     * Serialize array structure back to DSL text.
     *
     * @param array $data
     * @return string
     */
    public function serialize(array $data): string
    {
        $parts = [];

        // APP
        $app = $data['app'] ?? [];
        $appText = "APP {\n";
        foreach ($app as $k => $v) {
            $appText .= "  $k: " . $this->formatValue($v) . ";\n";
        }
        $appText .= "}\n";
        $parts[] = $appText;

        // STYLE
        $style = $data['style'] ?? [];
        $styleText = "STYLE {\n";
        foreach ($style as $k => $v) {
            $styleText .= "  $k: " . $this->formatValue($v) . ";\n";
        }
        $styleText .= "}\n";
        $parts[] = $styleText;

        // Questions
        foreach ($data['questions'] ?? [] as $q) {
            $qid = $q['id'] ?? uniqid('q');
            $type = $q['type'] ?? 'short_answer';
            $qText = "QUESTION \"$qid\" TYPE \"$type\" {\n";
            foreach ($q as $k => $v) {
                if (in_array($k, ['id', 'type'])) continue;
                if ($k === 'options' && is_array($v)) {
                    $qText .= "  options: [\n";
                    foreach ($v as $opt) {
                        $id = $opt['id'] ?? '';
                        $text = $opt['text'] ?? '';
                        $correct = isset($opt['correct']) ? ($opt['correct'] ? 'true' : 'false') : 'false';
                        $qText .= "    {id:\"$id\", text:\"" . $this->escape($text) . "\", correct:$correct},\n";
                    }
                    $qText .= "  ];\n";
                } elseif ($k === 'scoring' && is_array($v)) {
                    $qText .= "  scoring: {\n";
                    foreach ($v as $sk => $sv) {
                        $qText .= "    $sk: " . $this->formatValue($sv) . ";\n";
                    }
                    $qText .= "  };\n";
                } else {
                    $qText .= "  $k: " . $this->formatValue($v) . ";\n";
                }
            }
            $qText .= "}\n";
            $parts[] = $qText;
        }

        return implode("\n", $parts);
    }

    protected function formatValue($v): string
    {
        if (is_string($v)) return '"' . $this->escape($v) . '"';
        if (is_bool($v)) return $v ? 'true' : 'false';
        if (is_null($v)) return 'null';
        return (string)$v;
    }

    protected function escape(string $s): string
    {
        return str_replace('"', '\"', $s);
    }

    /**
     * Parse key: value; lines inside a block.
     */
    protected function parseKeyValues(string $block): array
    {
        $lines = preg_split('/\n/', trim($block));
        $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            // remove trailing semicolon
            if (substr($line, -1) === ';') $line = substr($line, 0, -1);
            // key: value
            if (strpos($line, ':') === false) continue;
            [$k, $v] = explode(':', $line, 2);
            $k = trim($k);
            $v = trim($v);
            // strip quotes
            if (preg_match('/^"(.*)"$/s', $v, $m)) {
                $v = str_replace('\\"', '"', $m[1]);
            } elseif ($v === 'true' || $v === 'false') {
                $v = $v === 'true';
            }
            $out[$k] = $v;
        }
        return $out;
    }

    protected function parseOptions(string $text): array
    {
        $opts = [];
        // split on '},{' or '},'
        if (preg_match_all('/\{([^}]*)\}/s', $text, $matches)) {
            foreach ($matches[1] as $m) {
                $kv = $this->parseKeyValues($m);
                if (isset($kv['id'])) $kv['id'] = trim($kv['id'], '"');
                if (isset($kv['text'])) $kv['text'] = trim($kv['text'], '"');
                if (isset($kv['correct'])) $kv['correct'] = ($kv['correct'] === 'true' || $kv['correct'] === true);
                $opts[] = $kv;
            }
        }
        return $opts;
    }
}