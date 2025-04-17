<?php
require_once '../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'POST method required.']);
    exit;
}

// Get POST data (expecting JSON)
$input = json_decode(file_get_contents('php://input'), true);
$postId = isset($input['id']) ? intval($input['id']) : 0;

if ($postId <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid post ID provided.']);
    exit;
}

$sql = "UPDATE posts SET view_count = view_count + 1 WHERE id = ?";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("Prepare failed (view count): (" . $conn->errno . ") " . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare view count update.']);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $postId);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'View count updated.']);
    } else {
        // Post might not exist, or count already updated in a race condition (less likely here)
        echo json_encode(['success' => false, 'message' => 'Post not found or view count not updated.']);
    }
} else {
    error_log("Execute failed (view count): (" . $stmt->errno . ") " . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to update view count.']);
}

$stmt->close();
$conn->close();
?>
