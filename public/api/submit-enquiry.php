<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Enable error logging but not display
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Function to send JSON response
function sendResponse($success, $message, $data = null) {
    $response = ['success' => $success, 'message' => $message];
    if ($data) $response['data'] = $data;
    echo json_encode($response);
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Invalid request method');
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

// Validate required fields
if (empty($input['name']) || empty($input['email']) || empty($input['phone']) || empty($input['message'])) {
    sendResponse(false, 'Please fill in all required fields');
}

// Validate email
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
    sendResponse(false, 'Please enter a valid email address');
}

// Sanitize inputs
$name = htmlspecialchars(strip_tags(trim($input['name'])));
$email = filter_var(trim($input['email']), FILTER_SANITIZE_EMAIL);
$phone = htmlspecialchars(strip_tags(trim($input['phone'])));
$service = isset($input['service']) ? htmlspecialchars(strip_tags(trim($input['service']))) : null;
$message = htmlspecialchars(strip_tags(trim($input['message'])));

// Database connection
try {
    $host = 'localhost';
    $dbname = 'ismano_db';
    $username = 'root';
    $password = 'mysql';
    
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if table exists, create if not
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'enquiries'");
    if ($tableCheck->rowCount() == 0) {
        // Create enquiries table
        $createSQL = "
            CREATE TABLE IF NOT EXISTS enquiries (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(100) NOT NULL,
                email VARCHAR(100) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                service VARCHAR(100),
                message TEXT,
                status ENUM('new', 'read', 'contacted', 'closed') DEFAULT 'new',
                priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_created (created_at)
            )
        ";
        $pdo->exec($createSQL);
    }
    
    // Insert enquiry
    $sql = "INSERT INTO enquiries (name, email, phone, service, message, priority) 
            VALUES (:name, :email, :phone, :service, :message, 'medium')";
    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':service' => $service,
        ':message' => $message
    ]);
    
    if ($result) {
        $enquiryId = $pdo->lastInsertId();
        
        // Log the enquiry
        error_log("New enquiry #{$enquiryId} from {$name} ({$email})");
        
        sendResponse(true, 'Thank you for your enquiry. We will get back to you within 24 hours.', ['id' => $enquiryId]);
    } else {
        sendResponse(false, 'Failed to save enquiry. Please try again.');
    }
    
} catch (PDOException $e) {
    error_log('Enquiry database error: ' . $e->getMessage());
    sendResponse(false, 'Unable to process your request. Please try again later.');
} catch (Exception $e) {
    error_log('Enquiry general error: ' . $e->getMessage());
    sendResponse(false, 'An unexpected error occurred. Please try again.');
}
?>