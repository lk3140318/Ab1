<?php
require_once '../config/db.php';

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$requestMethod = $_SERVER['REQUEST_METHOD'];

// --- Handle GET Request (Fetch Comments) ---
if ($requestMethod === 'GET') {
    $postId = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

    if ($postId <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid post ID for fetching comments.']);
        exit;
    }

    $comments = [];
    $sql = "SELECT id, username, comment, created_at
            FROM comments
            WHERE post_id = ?
            ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);

    if ($stmt === false) {
        error_log("Prepare failed (fetch comments): (" . $conn->errno . ") " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare statement for fetching comments.']);
        $conn->close(); exit;
    }

    $stmt->bind_param("i", $postId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $row['id'] = (int)$row['id']; // Ensure id is int
            $comments[] = $row;
        }
        $result->free();
    } else {
        error_log("Execute failed (fetch comments): (" . $stmt->errno . ") " . $stmt->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to fetch comments.']);
        $stmt->close(); $conn->close(); exit;
    }

    $stmt->close();
    $conn->close();
    echo json_encode($comments);
}

// --- Handle POST Request (Submit Comment) ---
elseif ($requestMethod === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    $postId = isset($input['post_id']) ? intval($input['post_id']) : 0;
    $username = isset($input['username']) ? trim($input['username']) : '';
    $commentText = isset($input['comment']) ? trim($input['comment']) : '';

    // Basic Validation
    if ($postId <= 0 || empty($username) || empty($commentText)) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required fields (post_id, username, comment).']);
        exit;
    }

    // Sanitize user input (important!)
    $username = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $commentText = htmlspecialchars($commentText, ENT_QUOTES, 'UTF-8');

    // Limit length (optional but good practice)
    if (strlen($username) > 100 || strlen($commentText) > 1000) {
         http_response_code(400);
        echo json_encode(['error' => 'Username or comment is too long.']);
        exit;
    }

    $sql = "INSERT INTO comments (post_id, username, comment) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);

     if ($stmt === false) {
        error_log("Prepare failed (insert comment): (" . $conn->errno . ") " . $conn->error);
        http_response_code(500);
        echo json_encode(['error' => 'Failed to prepare statement for saving comment.']);
        $conn->close(); exit;
    }

    $stmt->bind_param("iss", $postId, $username, $commentText);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            // Fetch the newly inserted comment to return it
            $newCommentId = $stmt->insert_id;
            $fetchSql = "SELECT id, username, comment, created_at FROM comments WHERE id = ?";
            $fetchStmt = $conn->prepare($fetchSql);
            $fetchStmt->bind_param("i", $newCommentId);
            $fetchStmt->execute();
            $result = $fetchStmt->get_result();
            $newComment = $result->fetch_assoc();
             $newComment['id'] = (int)$newComment['id'];
            $fetchStmt->close();

            http_response_code(201); // Created
            echo json_encode(['success' => true, 'comment' => $newComment]);

        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to save comment (0 rows affected).']);
        }
    } else {
        error_log("Execute failed (insert comment): (" . $stmt->errno . ") " . $stmt->error);
        // Check for foreign key constraint violation (post_id doesn't exist)
        if ($stmt->errno == 1452) { // MySQL error code for FK constraint violation
             http_response_code(400);
             echo json_encode(['error' => 'Invalid post ID. Cannot add comment to non-existent post.']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Database error while saving comment.']);
        }
    }

    $stmt->close();
    $conn->close();
}

// --- Handle Other Methods ---
else {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['error' => 'Method not supported. Use GET to fetch or POST to submit comments.']);
}
?>
