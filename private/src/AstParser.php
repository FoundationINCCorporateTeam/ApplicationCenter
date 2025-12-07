<?php
/**
 * .astappcnt File Parser
 * 
 * Parses custom .astappcnt DSL format into associative arrays
 */
class AstParser {
    private $content;
    private $position = 0;
    private $length;
    
    /**
     * Parse .astappcnt file
     * 
     * @param string $filePath Path to .astappcnt file
     * @return array Parsed configuration
     */
    public static function parseFile($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        
        $content = file_get_contents($filePath);
        $parser = new self();
        return $parser->parse($content);
    }
    
    /**
     * Parse .astappcnt content
     * 
     * @param string $content Content to parse
     * @return array Parsed configuration
     */
    public function parse($content) {
        $this->content = $content;
        $this->position = 0;
        $this->length = strlen($content);
        
        $result = [
            'app' => null,
            'style' => null,
            'questions' => []
        ];
        
        while ($this->position < $this->length) {
            $this->skipWhitespace();
            
            if ($this->position >= $this->length) {
                break;
            }
            
            $token = $this->readToken();
            
            if ($token === 'APP') {
                $result['app'] = $this->parseBlock();
            } elseif ($token === 'STYLE') {
                $result['style'] = $this->parseBlock();
            } elseif ($token === 'QUESTION') {
                $result['questions'][] = $this->parseQuestion();
            }
        }
        
        return $result;
    }
    
    /**
     * Parse a QUESTION block
     */
    private function parseQuestion() {
        $this->skipWhitespace();
        $id = $this->readString();
        
        $this->skipWhitespace();
        $this->expect('TYPE');
        
        $this->skipWhitespace();
        $type = $this->readString();
        
        $this->skipWhitespace();
        $properties = $this->parseBlock();
        
        return [
            'id' => $id,
            'type' => $type
        ] + $properties;
    }
    
    /**
     * Parse a block enclosed in {}
     */
    private function parseBlock() {
        $this->skipWhitespace();
        $this->expect('{');
        
        $properties = [];
        
        while ($this->position < $this->length) {
            $this->skipWhitespace();
            
            if ($this->peek() === '}') {
                $this->position++;
                break;
            }
            
            $key = $this->readToken();
            $this->skipWhitespace();
            $this->expect(':');
            $this->skipWhitespace();
            
            $value = $this->readValue();
            
            $properties[$key] = $value;
            
            $this->skipWhitespace();
            if ($this->peek() === ';') {
                $this->position++;
            }
        }
        
        return $properties;
    }
    
    /**
     * Read a value (string, number, array, or object)
     */
    private function readValue() {
        $this->skipWhitespace();
        $char = $this->peek();
        
        if ($char === '"') {
            return $this->readString();
        } elseif ($char === '[') {
            return $this->readArray();
        } elseif ($char === '{') {
            return $this->readObject();
        } elseif (is_numeric($char) || $char === '-') {
            return $this->readNumber();
        } elseif ($char === 't' || $char === 'f') {
            return $this->readBoolean();
        } else {
            return $this->readToken();
        }
    }
    
    /**
     * Read a quoted string
     */
    private function readString() {
        $this->expect('"');
        $start = $this->position;
        
        while ($this->position < $this->length) {
            $char = $this->content[$this->position];
            
            if ($char === '"' && $this->content[$this->position - 1] !== '\\') {
                $value = substr($this->content, $start, $this->position - $start);
                $this->position++;
                return stripcslashes($value);
            }
            
            $this->position++;
        }
        
        throw new Exception("Unterminated string");
    }
    
    /**
     * Read an array [...]
     */
    private function readArray() {
        $this->expect('[');
        $items = [];
        
        while ($this->position < $this->length) {
            $this->skipWhitespace();
            
            if ($this->peek() === ']') {
                $this->position++;
                break;
            }
            
            $items[] = $this->readValue();
            
            $this->skipWhitespace();
            if ($this->peek() === ',') {
                $this->position++;
            }
        }
        
        return $items;
    }
    
    /**
     * Read an object {...} (inline)
     */
    private function readObject() {
        $this->expect('{');
        $properties = [];
        
        while ($this->position < $this->length) {
            $this->skipWhitespace();
            
            if ($this->peek() === '}') {
                $this->position++;
                break;
            }
            
            $key = $this->readToken();
            $this->skipWhitespace();
            $this->expect(':');
            $this->skipWhitespace();
            $value = $this->readValue();
            
            $properties[$key] = $value;
            
            $this->skipWhitespace();
            $char = $this->peek();
            if ($char === ',' || $char === ';') {
                $this->position++;
            }
        }
        
        return $properties;
    }
    
    /**
     * Read a number
     */
    private function readNumber() {
        $start = $this->position;
        
        if ($this->peek() === '-') {
            $this->position++;
        }
        
        while ($this->position < $this->length && 
               (is_numeric($this->content[$this->position]) || 
                $this->content[$this->position] === '.')) {
            $this->position++;
        }
        
        $value = substr($this->content, $start, $this->position - $start);
        return strpos($value, '.') !== false ? floatval($value) : intval($value);
    }
    
    /**
     * Read a boolean
     */
    private function readBoolean() {
        $token = $this->readToken();
        return $token === 'true';
    }
    
    /**
     * Read a token (identifier)
     */
    private function readToken() {
        $start = $this->position;
        
        while ($this->position < $this->length) {
            $char = $this->content[$this->position];
            
            if (ctype_alnum($char) || $char === '_' || $char === '-') {
                $this->position++;
            } else {
                break;
            }
        }
        
        return substr($this->content, $start, $this->position - $start);
    }
    
    /**
     * Peek at current character
     */
    private function peek() {
        if ($this->position >= $this->length) {
            return null;
        }
        return $this->content[$this->position];
    }
    
    /**
     * Expect a specific character or token
     */
    private function expect($expected) {
        $this->skipWhitespace();
        
        $len = strlen($expected);
        $actual = substr($this->content, $this->position, $len);
        
        if ($actual !== $expected) {
            throw new Exception("Expected '$expected' but got '$actual' at position {$this->position}");
        }
        
        $this->position += $len;
    }
    
    /**
     * Skip whitespace and comments
     */
    private function skipWhitespace() {
        while ($this->position < $this->length) {
            $char = $this->content[$this->position];
            
            if (ctype_space($char)) {
                $this->position++;
            } elseif ($char === '/' && 
                     $this->position + 1 < $this->length && 
                     $this->content[$this->position + 1] === '/') {
                // Skip line comment
                while ($this->position < $this->length && 
                       $this->content[$this->position] !== "\n") {
                    $this->position++;
                }
            } else {
                break;
            }
        }
    }
}
