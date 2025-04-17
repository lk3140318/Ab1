<?php
// --- Database Configuration ---
define('DB_HOST', 'your_mysql_host'); // e.g., 'localhost' or from Koyeb/Render
define('DB_USER', 'your_mysql_username');
define('DB_PASS', 'your_mysql_password');
define('DB_NAME', 'movie_site');

// --- CORS Configuration ---
// IMPORTANT: Replace '*' with your GitHub Pages URL in production for better security
// e.g., 'https://yourusername.github.io'
define('ALLOWED_ORIGIN', '*');

// --- Site Configuration ---
// Optional: Define the base URL of your backend API if needed elsewhere
// define('API_BASE_URL', 'https://your-backend-url.koyeb.app/api/');

// --- Database Connection ---
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    // Don't echo sensitive errors in production
    error_log("Database Connection failed: " . $conn->connect_error);
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed. Please try again later.']);
    exit; // Stop script execution
}

// Set charset
$conn->set_charset("utf8mb4");

// --- CORS Headers ---
// Allow requests from the specified origin
header("Access-Control-Allow-Origin: " . ALLOWED_ORIGIN);
// Allow common methods
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
// Allow specific headers, including Content-Type for POST requests
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS requests (sent by browsers before POST/PUT/DELETE etc.)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

// --- Set Default Content Type ---
// Most API endpoints will return JSON
// Can be overridden in specific scripts if needed
header('Content-Type: application/json');

?>
