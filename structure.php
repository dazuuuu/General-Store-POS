<?php
/**
 * Project Structure Analyzer
 * Use this script to understand your project's architecture
 * Place this file in your project root directory and run via CLI or browser
 */

class ProjectStructureAnalyzer
{
    private $rootPath;
    private $analysis = [];
    private $fileExtensions = ['php', 'html', 'js', 'css', 'json', 'xml', 'yml', 'yaml', 'env'];
    private $excludeDirs = ['vendor', 'node_modules', '.git', 'cache', 'logs', 'tmp', 'storage/framework'];

    public function __construct($rootPath = __DIR__)
    {
        $this->rootPath = realpath($rootPath);
        $this->analysis = [
            'directories' => [],
            'files' => [],
            'controllers' => [],
            'models' => [],
            'views' => [],
            'routes' => [],
            'configurations' => [],
            'database' => [],
            'third_party' => [],
            'custom_classes' => [],
            'dependencies' => [],
            'entry_points' => [],
            'api_endpoints' => [],
            'middleware' => [],
            'helpers' => [],
            'traits' => [],
            'interfaces' => [],
            'abstract_classes' => [],
            'file_counts' => [],
            'total_size' => 0,
            'total_files' => 0,
            'total_dirs' => 0
        ];
    }

    public function analyze()
    {
        echo "🔍 Starting project structure analysis...\n\n";
        $this->scanDirectory($this->rootPath);
        $this->analyzeFileContents();
        $this->generateReport();
        return $this->analysis;
    }

    private function scanDirectory($path, $depth = 0)
    {
        if ($depth > 5) return; // Limit depth for performance

        $items = scandir($path);
        $dirName = basename($path);
        $relativePath = str_replace($this->rootPath, '', $path);

        // Skip excluded directories
        foreach ($this->excludeDirs as $exclude) {
            if (strpos($path, DIRECTORY_SEPARATOR . $exclude) !== false) {
                return;
            }
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            $relativePath = str_replace($this->rootPath, '', $fullPath);

            if (is_dir($fullPath)) {
                $this->analysis['total_dirs']++;
                $this->analysis['directories'][$relativePath] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'type' => $this->detectDirectoryType($item, $relativePath)
                ];
                $this->scanDirectory($fullPath, $depth + 1);
            } else {
                $this->analyzeFile($fullPath, $item, $relativePath);
            }
        }
    }

    private function analyzeFile($fullPath, $fileName, $relativePath)
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        
        if (!in_array($extension, $this->fileExtensions)) {
            return;
        }

        $this->analysis['total_files']++;
        $fileSize = filesize($fullPath);
        $this->analysis['total_size'] += $fileSize;

        // Categorize files
        $fileInfo = [
            'name' => $fileName,
            'path' => $relativePath,
            'size' => $fileSize,
            'extension' => $extension,
            'modified' => date('Y-m-d H:i:s', filemtime($fullPath))
        ];

        // Categorize by type and purpose
        if (strpos($fileName, 'Controller') !== false || strpos($relativePath, 'controller') !== false) {
            $this->analysis['controllers'][$relativePath] = $fileInfo;
        }

        if (strpos($fileName, 'Model') !== false || strpos($relativePath, 'model') !== false) {
            $this->analysis['models'][$relativePath] = $fileInfo;
        }

        if (strpos($relativePath, 'view') !== false || strpos($relativePath, 'template') !== false) {
            $this->analysis['views'][$relativePath] = $fileInfo;
        }

        if (strpos($fileName, 'route') !== false || strpos($relativePath, 'route') !== false) {
            $this->analysis['routes'][$relativePath] = $fileInfo;
        }

        if (strpos($relativePath, 'config') !== false || strpos($fileName, 'config') !== false) {
            $this->analysis['configurations'][$relativePath] = $fileInfo;
        }

        if (strpos($relativePath, 'database') !== false || strpos($relativePath, 'migration') !== false) {
            $this->analysis['database'][$relativePath] = $fileInfo;
        }

        if (strpos($relativePath, 'vendor') !== false || strpos($relativePath, 'node_modules') !== false) {
            $this->analysis['third_party'][$relativePath] = $fileInfo;
        }

        // Detect entry points
        if (in_array($fileName, ['index.php', 'main.php', 'app.php', 'bootstrap.php'])) {
            $this->analysis['entry_points'][$relativePath] = $fileInfo;
        }

        // Detect helpers and utilities
        if (strpos($relativePath, 'helper') !== false || strpos($relativePath, 'util') !== false) {
            $this->analysis['helpers'][$relativePath] = $fileInfo;
        }

        // Track file counts by extension
        if (!isset($this->analysis['file_counts'][$extension])) {
            $this->analysis['file_counts'][$extension] = 0;
        }
        $this->analysis['file_counts'][$extension]++;

        // Read PHP files to detect classes and dependencies
        if ($extension === 'php') {
            $this->extractPHPInfo($fullPath, $relativePath);
        }
    }

    private function extractPHPInfo($fullPath, $relativePath)
    {
        $content = file_get_contents($fullPath);
        
        // Detect namespace
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = trim($matches[1]);
            $this->analysis['custom_classes'][$relativePath] = [
                'namespace' => $namespace,
                'class_name' => basename($fullPath, '.php')
            ];
        }

        // Detect class types
        if (strpos($content, 'abstract class') !== false) {
            $this->analysis['abstract_classes'][$relativePath] = true;
        }
        if (strpos($content, 'trait') !== false) {
            $this->analysis['traits'][$relativePath] = true;
        }
        if (strpos($content, 'interface') !== false) {
            $this->analysis['interfaces'][$relativePath] = true;
        }
        if (strpos($content, 'middleware') !== false || strpos($relativePath, 'middleware') !== false) {
            $this->analysis['middleware'][$relativePath] = true;
        }

        // Detect dependencies (use statements)
        preg_match_all('/use\s+([^;]+);/', $content, $matches);
        if (!empty($matches[1])) {
            foreach ($matches[1] as $use) {
                $this->analysis['dependencies'][$relativePath][] = trim($use);
            }
        }

        // Detect API endpoints in controllers
        if (strpos($relativePath, 'controller') !== false) {
            preg_match_all('/@(Get|Post|Put|Delete|Patch|Route)\s*\(\s*[\'"]([^\'"]+)[\'"]\s*\)/', $content, $apiMatches);
            if (!empty($apiMatches[2])) {
                $this->analysis['api_endpoints'][$relativePath] = $apiMatches[2];
            }
        }
    }

    private function detectDirectoryType($dirName, $relativePath)
    {
        $types = [
            'controller' => ['controller', 'ctl', 'action'],
            'model' => ['model', 'entity', 'repository'],
            'view' => ['view', 'template', 'theme', 'layout'],
            'config' => ['config', 'conf', 'settings'],
            'database' => ['database', 'db', 'migration', 'seed'],
            'public' => ['public', 'web', 'assets', 'static'],
            'test' => ['test', 'tests', 'spec'],
            'library' => ['lib', 'library', 'core', 'src'],
            'service' => ['service', 'services', 'business'],
            'helper' => ['helper', 'util', 'tool'],
            'middleware' => ['middleware'],
            'api' => ['api', 'rest', 'graphql']
        ];

        foreach ($types as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if (stripos($dirName, $keyword) !== false || stripos($relativePath, $keyword) !== false) {
                    return $type;
                }
            }
        }

        return 'other';
    }

    private function analyzeFileContents()
    {
        // Additional file content analysis can be added here
        // For example, detecting framework usage, database connections, etc.
    }

    private function generateReport()
    {
        echo "\n📊 PROJECT STRUCTURE REPORT\n";
        echo "=============================\n\n";

        echo "📁 Directory Structure:\n";
        echo "----------------------\n";
        foreach ($this->analysis['directories'] as $path => $info) {
            echo "  ├─ {$info['name']} ({$info['type']}) - {$path}\n";
        }

        echo "\n📄 File Statistics:\n";
        echo "------------------\n";
        echo "  Total Files: {$this->analysis['total_files']}\n";
        echo "  Total Directories: {$this->analysis['total_dirs']}\n";
        echo "  Total Size: " . $this->formatSize($this->analysis['total_size']) . "\n";

        echo "\n📋 File Types:\n";
        echo "--------------\n";
        foreach ($this->analysis['file_counts'] as $ext => $count) {
            echo "  .{$ext}: {$count} files\n";
        }

        echo "\n🎯 Key Components Found:\n";
        echo "----------------------\n";
        echo "  Controllers: " . count($this->analysis['controllers']) . "\n";
        echo "  Models: " . count($this->analysis['models']) . "\n";
        echo "  Views: " . count($this->analysis['views']) . "\n";
        echo "  Routes: " . count($this->analysis['routes']) . "\n";
        echo "  Configurations: " . count($this->analysis['configurations']) . "\n";
        echo "  Database Files: " . count($this->analysis['database']) . "\n";
        echo "  Entry Points: " . count($this->analysis['entry_points']) . "\n";
        echo "  Middleware: " . count($this->analysis['middleware']) . "\n";
        echo "  Helpers: " . count($this->analysis['helpers']) . "\n";

        if (!empty($this->analysis['api_endpoints'])) {
            echo "\n🌐 API Endpoints Found:\n";
            echo "----------------------\n";
            foreach ($this->analysis['api_endpoints'] as $file => $endpoints) {
                echo "  {$file}:\n";
                foreach ($endpoints as $endpoint) {
                    echo "    - {$endpoint}\n";
                }
            }
        }

        echo "\n📦 Dependencies Map:\n";
        echo "--------------------\n";
        $depCount = 0;
        foreach ($this->analysis['dependencies'] as $file => $deps) {
            if ($depCount < 20) { // Limit output for readability
                echo "  {$file}:\n";
                foreach ($deps as $dep) {
                    echo "    - {$dep}\n";
                }
                $depCount++;
            }
        }

        echo "\n🔍 Custom Classes:\n";
        echo "------------------\n";
        $classCount = 0;
        foreach ($this->analysis['custom_classes'] as $file => $info) {
            if ($classCount < 20) { // Limit output for readability
                echo "  {$info['namespace']}\\{$info['class_name']} ({$file})\n";
                $classCount++;
            }
        }

        echo "\n📦 Third-party Dependencies:\n";
        echo "---------------------------\n";
        if (!empty($this->analysis['third_party'])) {
            foreach ($this->analysis['third_party'] as $path => $info) {
                echo "  - {$path}\n";
            }
        } else {
            echo "  No third-party libraries detected\n";
        }

        // Recommendations for new features
        echo "\n💡 Recommendations for Adding New Features:\n";
        echo "==========================================\n";
        $this->generateRecommendations();
    }

    private function generateRecommendations()
    {
        echo "\n📦 To implement STOCK MANAGEMENT:\n";
        echo "  1. Add a 'Stock' model to handle inventory\n";
        echo "  2. Create stock controllers for CRUD operations\n";
        echo "  3. Add stock views/templates\n";
        echo "  4. Consider adding stock migration files\n";
        echo "  5. Implement stock-related API endpoints\n";
        
        if (isset($this->analysis['directories'])) {
            $suggestedPaths = $this->findSuggestedPaths();
            echo "  6. Recommended locations:\n";
            echo "     - Models: {$suggestedPaths['models']}/Stock.php\n";
            echo "     - Controllers: {$suggestedPaths['controllers']}/StockController.php\n";
            echo "     - Views: {$suggestedPaths['views']}/stock/\n";
            echo "     - Routes: {$suggestedPaths['routes']}/stock.php\n";
        }

        echo "\n👥 To implement STAFF MANAGEMENT:\n";
        echo "  1. Add a 'Staff' model (could extend User model)\n";
        echo "  2. Create staff controllers with role-based access\n";
        echo "  3. Add staff views for management\n";
        echo "  4. Implement staff authentication/authorization\n";
        echo "  5. Add staff permissions system\n";
        
        if (isset($this->analysis['directories'])) {
            $suggestedPaths = $this->findSuggestedPaths();
            echo "  6. Recommended locations:\n";
            echo "     - Models: {$suggestedPaths['models']}/Staff.php\n";
            echo "     - Controllers: {$suggestedPaths['controllers']}/StaffController.php\n";
            echo "     - Views: {$suggestedPaths['views']}/staff/\n";
            echo "     - Middleware: {$suggestedPaths['middleware']}/AuthMiddleware.php\n";
        }

        echo "\n🔧 General Recommendations:\n";
        echo "  1. Check existing database schema for compatibility\n";
        echo "  2. Review current routing structure\n";
        echo "  3. Consider creating API versioning\n";
        echo "  4. Add proper validation for new features\n";
        echo "  5. Implement logging for new operations\n";
        echo "  6. Write unit tests for new features\n";
    }

    private function findSuggestedPaths()
    {
        $paths = [
            'models' => 'app/Models',
            'controllers' => 'app/Http/Controllers',
            'views' => 'resources/views',
            'routes' => 'routes',
            'middleware' => 'app/Http/Middleware'
        ];

        // Try to find actual paths
        foreach ($this->analysis['directories'] as $path => $info) {
            foreach ($paths as $key => $default) {
                if (stripos($path, $key) !== false && $info['type'] === $key) {
                    $paths[$key] = ltrim($path, '/\\');
                }
            }
        }

        return $paths;
    }

    private function formatSize($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    public function saveReport($filename = 'project_analysis.json')
    {
        file_put_contents($filename, json_encode($this->analysis, JSON_PRETTY_PRINT));
        echo "\n✅ Report saved to: {$filename}\n";
    }

    public function generateVisualMap()
    {
        // Generate a simple visual tree of the project
        echo "\n🌳 Project Structure Tree:\n";
        echo "=========================\n\n";
        $this->printTree($this->rootPath);
    }

    private function printTree($path, $prefix = '')
    {
        $items = scandir($path);
        $dirs = [];
        $files = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            if (in_array($item, $this->excludeDirs)) continue;
            
            if (is_dir($path . DIRECTORY_SEPARATOR . $item)) {
                $dirs[] = $item;
            } else {
                $files[] = $item;
            }
        }

        sort($dirs);
        sort($files);

        $allItems = array_merge($dirs, $files);
        $lastIndex = count($allItems) - 1;

        foreach ($allItems as $index => $item) {
            $isLast = ($index === $lastIndex);
            $prefix1 = $isLast ? '└── ' : '├── ';
            $prefix2 = $isLast ? '    ' : '│   ';

            if (is_dir($path . DIRECTORY_SEPARATOR . $item)) {
                echo $prefix . $prefix1 . "📁 {$item}/\n";
                $this->printTree($path . DIRECTORY_SEPARATOR . $item, $prefix . $prefix2);
            } else {
                $extension = pathinfo($item, PATHINFO_EXTENSION);
                $icon = $this->getFileIcon($extension);
                echo $prefix . $prefix1 . "{$icon} {$item}\n";
            }
        }
    }

    private function getFileIcon($extension)
    {
        $icons = [
            'php' => '🐘',
            'js' => '📜',
            'css' => '🎨',
            'html' => '🌐',
            'json' => '📋',
            'xml' => '📄',
            'yml' => '⚙️',
            'yaml' => '⚙️',
            'env' => '🔐',
            'txt' => '📝',
            'md' => '📖',
            'sql' => '🗄️',
            'sh' => '💻'
        ];
        return $icons[$extension] ?? '📄';
    }
}

// ============================================
// EXECUTION
// ============================================

echo "\n";
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║     PROJECT STRUCTURE ANALYZER - PHP Edition             ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n";

// Initialize analyzer
$analyzer = new ProjectStructureAnalyzer(__DIR__);

// Run analysis
$analysis = $analyzer->analyze();

// Generate visual tree
$analyzer->generateVisualMap();

// Save detailed report
$analyzer->saveReport();

echo "\n✨ Analysis complete! Use this information to plan your new features.\n";
echo "📝 Check 'project_analysis.json' for detailed data.\n\n";