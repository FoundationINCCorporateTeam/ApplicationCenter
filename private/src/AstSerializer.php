<?php
/**
 * .astappcnt File Serializer
 * 
 * Serializes associative arrays back to .astappcnt DSL format
 */
class AstSerializer {
    private $indent = 0;
    private $indentSize = 2;
    
    /**
     * Serialize to .astappcnt file
     * 
     * @param array $data Data to serialize
     * @param string $filePath Path to save file
     * @return bool Success status
     */
    public static function serializeToFile($data, $filePath) {
        $serializer = new self();
        $content = $serializer->serialize($data);
        
        $dir = dirname($filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return file_put_contents($filePath, $content) !== false;
    }
    
    /**
     * Serialize to .astappcnt format
     * 
     * @param array $data Data to serialize
     * @return string Serialized content
     */
    public function serialize($data) {
        $output = [];
        
        // APP block
        if (isset($data['app']) && is_array($data['app'])) {
            $output[] = $this->serializeBlock('APP', $data['app']);
        }
        
        // STYLE block
        if (isset($data['style']) && is_array($data['style'])) {
            $output[] = $this->serializeBlock('STYLE', $data['style']);
        }
        
        // QUESTION blocks
        if (isset($data['questions']) && is_array($data['questions'])) {
            foreach ($data['questions'] as $question) {
                $output[] = $this->serializeQuestion($question);
            }
        }
        
        return implode("\n\n", $output) . "\n";
    }
    
    /**
     * Serialize a QUESTION block
     */
    private function serializeQuestion($question) {
        $id = $question['id'] ?? '';
        $type = $question['type'] ?? '';
        
        // Remove id and type from properties
        $properties = $question;
        unset($properties['id']);
        unset($properties['type']);
        
        $output = "QUESTION \"{$id}\" TYPE \"{$type}\" {\n";
        $this->indent++;
        
        foreach ($properties as $key => $value) {
            $output .= $this->getIndent() . "{$key}: ";
            $output .= $this->serializeValue($value);
            $output .= ";\n";
        }
        
        $this->indent--;
        $output .= "}";
        
        return $output;
    }
    
    /**
     * Serialize a named block
     */
    private function serializeBlock($name, $properties) {
        $output = "{$name} {\n";
        $this->indent++;
        
        foreach ($properties as $key => $value) {
            $output .= $this->getIndent() . "{$key}: ";
            $output .= $this->serializeValue($value);
            $output .= ";\n";
        }
        
        $this->indent--;
        $output .= "}";
        
        return $output;
    }
    
    /**
     * Serialize a value
     */
    private function serializeValue($value) {
        if (is_string($value)) {
            return '"' . addcslashes($value, '"\\') . '"';
        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        } elseif (is_numeric($value)) {
            return (string)$value;
        } elseif (is_array($value)) {
            // Check if it's an associative array (object) or indexed array
            if ($this->isAssoc($value)) {
                return $this->serializeObject($value);
            } else {
                return $this->serializeArray($value);
            }
        } else {
            return 'null';
        }
    }
    
    /**
     * Serialize an array
     */
    private function serializeArray($array) {
        if (empty($array)) {
            return '[]';
        }
        
        $items = [];
        foreach ($array as $item) {
            $items[] = $this->serializeValue($item);
        }
        
        // For short arrays, keep on one line
        $inline = '[' . implode(', ', $items) . ']';
        if (strlen($inline) <= 80) {
            return $inline;
        }
        
        // For long arrays, use multiple lines
        $this->indent++;
        $output = "[\n";
        foreach ($items as $item) {
            $output .= $this->getIndent() . $item . ",\n";
        }
        $this->indent--;
        $output .= $this->getIndent() . ']';
        
        return $output;
    }
    
    /**
     * Serialize an object (associative array)
     */
    private function serializeObject($object) {
        if (empty($object)) {
            return '{}';
        }
        
        $items = [];
        foreach ($object as $key => $value) {
            $items[] = "{$key}:" . $this->serializeValue($value);
        }
        
        return '{' . implode(', ', $items) . '}';
    }
    
    /**
     * Check if array is associative
     */
    private function isAssoc($array) {
        if (!is_array($array) || empty($array)) {
            return false;
        }
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    /**
     * Get current indentation
     */
    private function getIndent() {
        return str_repeat(' ', $this->indent * $this->indentSize);
    }
}
