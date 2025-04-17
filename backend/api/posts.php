<?php
require_once '../config/db.php'; // Adjust path as needed

// Basic error handling example
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors publicly in production
ini_set('log_errors', 1);    // Log errors instead

$posts = [];
$sql = "SELECT id, title, image_url, link_480p, link_720p, link_1080p, created_at, view_count
        FROM posts
        ORDER BY created_at DESC"; // Add LIMIT and OFFSET here for pagination later

$result = $conn->query($sql);

if ($result) {
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            // Ensure numeric types are numbers in JSON
            $row['id'] = (int)$row['id'];
            $row['view_count'] = (int)$row['view_count'];
            $posts[] = $row;
        }
    }
    $result->free();
} else {
    error_log("Error fetching posts: " . $conn->error);
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch posts']);
    $conn->close();
    exit;
}

$conn->close();
echo json_encode($posts);
?>
