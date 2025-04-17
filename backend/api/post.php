<?php
require_once '../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$postId = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($postId <= 0) {
    http_response_code(400); // Bad Request
    echo json_encode(['error' => 'Invalid post ID specified.']);
    exit;
}

$post = null;
$sql = "SELECT id, title, description, image_url, link_480p, link_720p, link_1080p, view_count, created_at
        FROM posts
        WHERE id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    error_log("Prepare failed: (" . $conn->errno . ") " . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare statement']);
    $conn->close();
    exit;
}

$stmt->bind_param("i", $postId);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $post = $result->fetch_assoc();
        // Ensure numeric types are numbers
        $post['id'] = (int)$post['id'];
        $post['view_count'] = (int)$post['view_count'];
    } else {
        http_response_code(404); // Not Found
        echo json_encode(['error' => 'Post not found.']);
        $stmt->close();
        $conn->close();
        exit;
    }
    $result->free();
} else {
    error_log("Execute failed: (" . $stmt->errno . ") " . $stmt->error);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch post details.']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();
$conn->close();

if ($post) {
    echo json_encode($post);
}
// If $post is still null, an error occurred and was handled above
?>
