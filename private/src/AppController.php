<?php
/**
 * Application Controller
 * 
 * Handles creating, saving, loading, and managing applications
 */
class AppController {
    private $dataDir;
    
    public function __construct() {
        $this->dataDir = __DIR__ . '/../data/apps';
        
        if (!is_dir($this->dataDir)) {
            mkdir($this->dataDir, 0755, true);
        }
    }
    
    /**
     * Create a new application
     */
    public function createApp($data) {
        $validation = validateRequired($data, ['name', 'group_id']);
        if ($validation !== true) {
            return ['error' => $validation];
        }
        
        $id = $data['id'] ?? generateId();
        
        $appData = [
            'app' => [
                'id' => $id,
                'name' => sanitize($data['name']),
                'description' => sanitize($data['description'] ?? ''),
                'group_id' => intval($data['group_id']),
                'target_role' => sanitize($data['target_role'] ?? ''),
                'pass_score' => intval($data['pass_score'] ?? 70)
            ],
            'style' => $data['style'] ?? $this->getDefaultStyle(),
            'questions' => $data['questions'] ?? []
        ];
        
        // Save to .astappcnt file
        $filePath = "{$this->dataDir}/{$id}.astappcnt";
        
        if (!AstSerializer::serializeToFile($appData, $filePath)) {
            return ['error' => 'Failed to save application'];
        }
        
        return [
            'success' => true,
            'id' => $id,
            'message' => 'Application created successfully'
        ];
    }
    
    /**
     * Save/update an existing application
     */
    public function saveApp($data) {
        if (!isset($data['id'])) {
            return $this->createApp($data);
        }
        
        $id = sanitize($data['id']);
        $filePath = "{$this->dataDir}/{$id}.astappcnt";
        
        $appData = [
            'app' => $data['app'] ?? [],
            'style' => $data['style'] ?? $this->getDefaultStyle(),
            'questions' => $data['questions'] ?? []
        ];
        
        if (!AstSerializer::serializeToFile($appData, $filePath)) {
            return ['error' => 'Failed to save application'];
        }
        
        return [
            'success' => true,
            'id' => $id,
            'message' => 'Application saved successfully'
        ];
    }
    
    /**
     * Load an application
     */
    public function loadApp($id) {
        $id = sanitize($id);
        $filePath = "{$this->dataDir}/{$id}.astappcnt";
        
        if (!file_exists($filePath)) {
            return ['error' => 'Application not found'];
        }
        
        try {
            $data = AstParser::parseFile($filePath);
            
            return [
                'success' => true,
                'data' => $data
            ];
        } catch (Exception $e) {
            return ['error' => 'Failed to load application: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get application config for Roblox client
     */
    public function getConfig($id) {
        $result = $this->loadApp($id);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        // Return in a format suitable for Roblox
        return [
            'success' => true,
            'config' => $result['data']
        ];
    }
    
    /**
     * List all applications
     */
    public function listApps() {
        $files = glob("{$this->dataDir}/*.astappcnt");
        $apps = [];
        
        foreach ($files as $file) {
            try {
                $data = AstParser::parseFile($file);
                $apps[] = [
                    'id' => $data['app']['id'] ?? basename($file, '.astappcnt'),
                    'name' => $data['app']['name'] ?? 'Untitled',
                    'description' => $data['app']['description'] ?? '',
                    'group_id' => $data['app']['group_id'] ?? 0,
                    'question_count' => count($data['questions'] ?? [])
                ];
            } catch (Exception $e) {
                // Skip invalid files
                continue;
            }
        }
        
        return [
            'success' => true,
            'apps' => $apps
        ];
    }
    
    /**
     * Delete an application
     */
    public function deleteApp($id) {
        $id = sanitize($id);
        $filePath = "{$this->dataDir}/{$id}.astappcnt";
        
        if (!file_exists($filePath)) {
            return ['error' => 'Application not found'];
        }
        
        if (!unlink($filePath)) {
            return ['error' => 'Failed to delete application'];
        }
        
        return [
            'success' => true,
            'message' => 'Application deleted successfully'
        ];
    }
    
    /**
     * Get default style configuration
     */
    private function getDefaultStyle() {
        return [
            'primary_color' => '#ff4b6e',
            'secondary_color' => '#1f2933',
            'background' => 'gradient:linear,#1f2933,#111827',
            'font' => 'Inter',
            'button_shape' => 'pill'
        ];
    }
}
