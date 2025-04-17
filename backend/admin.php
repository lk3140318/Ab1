<?php
session_start(); // Start session for login state
require_once 'config/db.php'; // Adjust path

// --- Configuration ---
$admin_page_url = 'admin.php'; // Self-referencing URL

// --- Helper Functions ---
function redirect($url) {
    header("Location: " . $url);
    exit;
}

function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = ['type' => $type, 'message' => $message];
}

function display_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $msg = $_SESSION['flash_message'];
        echo '<div class="alert alert-' . htmlspecialchars($msg['type']) . ' alert-dismissible fade show" role="alert">
                ' . htmlspecialchars($msg['message']) . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['flash_message']); // Clear after displaying
    }
}

// --- Logout Action ---
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    redirect($admin_page_url);
}

// --- Login Handling ---
$login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $login_error = 'Username and password are required.';
    } else {
        $sql = "SELECT id, username, password FROM admin_users WHERE username = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            // Verify password using bcrypt
            if (password_verify($password, $user['password'])) {
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_username'] = $user['username'];
                $_SESSION['admin_id'] = $user['id'];
                redirect($admin_page_url); // Redirect to dashboard
            } else {
                $login_error = 'Invalid username or password.';
            }
        } else {
            $login_error = 'Invalid username or password.';
        }
        $stmt->close();
    }
}

// --- Check if Admin is Logged In ---
$is_admin_logged_in = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// --- Admin Actions (CRUD) - Requires Login ---
if ($is_admin_logged_in) {
    $action = $_REQUEST['action'] ?? 'dashboard'; // Use $_REQUEST to catch GET/POST

    // -- Delete Post --
    if ($action === 'delete_post' && isset($_GET['id'])) {
        $postId = intval($_GET['id']);
        if ($postId > 0) {
            // Also delete associated comments (handled by FOREIGN KEY ON DELETE CASCADE)
            $sql = "DELETE FROM posts WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $postId);
            if ($stmt->execute()) {
                set_flash_message('success', 'Post deleted successfully.');
            } else {
                 set_flash_message('danger', 'Error deleting post: ' . $stmt->error);
            }
            $stmt->close();
            redirect($admin_page_url . '?action=manage_posts');
        }
    }

    // -- Delete Comment --
    if ($action === 'delete_comment' && isset($_GET['id'])) {
        $commentId = intval($_GET['id']);
         if ($commentId > 0) {
            $sql = "DELETE FROM comments WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $commentId);
             if ($stmt->execute()) {
                set_flash_message('success', 'Comment deleted successfully.');
            } else {
                 set_flash_message('danger', 'Error deleting comment: ' . $stmt->error);
            }
            $stmt->close();
            redirect($admin_page_url . '?action=manage_comments');
        }
    }

    // -- Add/Edit Post Handling --
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_post'])) {
        $postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $image_url = trim($_POST['image_url'] ?? ''); // Add validation (is URL?)
        $link_480p = trim($_POST['link_480p'] ?? '');
        $link_720p = trim($_POST['link_720p'] ?? '');
        $link_1080p = trim($_POST['link_1080p'] ?? '');

        // Basic Validation
        if (empty($title) || empty($image_url)) {
             set_flash_message('danger', 'Title and Image URL are required.');
             // Redirect back to form, potentially preserving data via session if needed
             redirect($admin_page_url . '?action=' . ($postId ? 'edit_post&id='.$postId : 'add_post'));
        } else {
            if ($postId > 0) { // Update existing post
                $sql = "UPDATE posts SET title=?, description=?, image_url=?, link_480p=?, link_720p=?, link_1080p=? WHERE id=?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssi", $title, $description, $image_url, $link_480p, $link_720p, $link_1080p, $postId);
                $success_msg = 'Post updated successfully.';
                $error_msg = 'Error updating post: ';
                $redirect_action = 'manage_posts';
            } else { // Insert new post
                $sql = "INSERT INTO posts (title, description, image_url, link_480p, link_720p, link_1080p) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssss", $title, $description, $image_url, $link_480p, $link_720p, $link_1080p);
                $success_msg = 'Post added successfully.';
                $error_msg = 'Error adding post: ';
                $redirect_action = 'manage_posts';
            }

            if ($stmt->execute()) {
                set_flash_message('success', $success_msg);
            } else {
                 set_flash_message('danger', $error_msg . $stmt->error);
                 // Redirect back to the form on error if needed
                 $redirect_action = ($postId ? 'edit_post&id='.$postId : 'add_post');
            }
            $stmt->close();
            redirect($admin_page_url . '?action=' . $redirect_action);
        }
    }
} // End of actions requiring login

// --- HTML Output ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .navbar { background-color: #232f3e; }
        .navbar-brand, .nav-link { color: #fff !important; }
        .sidebar { background-color: #fff; min-height: calc(100vh - 56px); padding-top: 1rem; border-right: 1px solid #dee2e6;}
        .content { padding: 2rem; }
        .card { margin-bottom: 1.5rem; }
        .form-label { font-weight: 500; }
    </style>
</head>
<body>

<?php if (!$is_admin_logged_in): // --- LOGIN FORM --- ?>

<div class="container">
    <div class="row justify-content-center align-items-center vh-100">
        <div class="col-md-6 col-lg-4">
            <div class="card shadow">
                <div class="card-body p-4">
                    <h3 class="card-title text-center mb-4">Admin Login</h3>
                    <?php if ($login_error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($login_error); ?></div>
                    <?php endif; ?>
                    <form method="POST" action="<?php echo htmlspecialchars($admin_page_url); ?>">
                        <input type="hidden" name="login" value="1">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-warning w-100">Login</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php else: // --- ADMIN DASHBOARD --- ?>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo $admin_page_url; ?>">Admin Panel</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="?action=logout">Logout</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebar" class="col-md-3 col-lg-2 d-md-block sidebar collapse">
            <div class="position-sticky">
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($action === 'dashboard' || !$action) ? 'active' : ''; ?>" href="?action=dashboard">
                            Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($action === 'add_post') ? 'active' : ''; ?>" href="?action=add_post">
                            Add New Post
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($action === 'manage_posts' || $action === 'edit_post') ? 'active' : ''; ?>" href="?action=manage_posts">
                            Manage Posts
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($action === 'manage_comments') ? 'active' : ''; ?>" href="?action=manage_comments">
                            Manage Comments
                        </a>
                    </li>
                     <!-- Add more sections like Manage Users if needed -->
                </ul>
            </div>
        </nav>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 content">
            <?php display_flash_message(); // Display session flash messages ?>

            <?php // --- Content based on action ---

            // -- Dashboard View --
            if ($action === 'dashboard') {
                // Fetch some stats
                $postCountResult = $conn->query("SELECT COUNT(*) as count FROM posts");
                $postCount = $postCountResult ? $postCountResult->fetch_assoc()['count'] : 0;
                $commentCountResult = $conn->query("SELECT COUNT(*) as count FROM comments");
                $commentCount = $commentCountResult ? $commentCountResult->fetch_assoc()['count'] : 0;
                $totalViewsResult = $conn->query("SELECT SUM(view_count) as total FROM posts");
                $totalViews = $totalViewsResult ? $totalViewsResult->fetch_assoc()['total'] : 0;

                echo '<h2>Dashboard</h2>';
                echo '<div class="row">';
                echo '<div class="col-md-4"><div class="card"><div class="card-body text-center"><h5>Total Posts</h5><p class="fs-3">'.htmlspecialchars($postCount).'</p></div></div></div>';
                 echo '<div class="col-md-4"><div class="card"><div class="card-body text-center"><h5>Total Comments</h5><p class="fs-3">'.htmlspecialchars($commentCount).'</p></div></div></div>';
                 echo '<div class="col-md-4"><div class="card"><div class="card-body text-center"><h5>Total Views</h5><p class="fs-3">'.htmlspecialchars($totalViews ?? 0).'</p></div></div></div>';
                echo '</div>';
                 echo '<h4>Quick Actions</h4>';
                 echo '<a href="?action=add_post" class="btn btn-primary me-2">Add Post</a>';
                 echo '<a href="?action=manage_posts" class="btn btn-secondary me-2">Manage Posts</a>';
                 echo '<a href="?action=manage_comments" class="btn btn-info">Manage Comments</a>';
            }

            // -- Add/Edit Post Form --
            elseif ($action === 'add_post' || $action === 'edit_post') {
                $post_data = [ // Default empty values for add form
                    'id' => 0, 'title' => '', 'description' => '', 'image_url' => '',
                    'link_480p' => '', 'link_720p' => '', 'link_1080p' => ''
                ];
                $form_title = 'Add New Post';

                if ($action === 'edit_post' && isset($_GET['id'])) {
                    $postId = intval($_GET['id']);
                    $sql = "SELECT * FROM posts WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param("i", $postId);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows === 1) {
                        $post_data = $result->fetch_assoc();
                        $form_title = 'Edit Post: ' . htmlspecialchars($post_data['title']);
                    } else {
                        set_flash_message('warning', 'Post not found for editing.');
                        redirect($admin_page_url . '?action=manage_posts');
                    }
                    $stmt->close();
                }

                echo '<h2>' . $form_title . '</h2>';
                echo '<form method="POST" action="' . htmlspecialchars($admin_page_url) . '">';
                echo '<input type="hidden" name="save_post" value="1">';
                echo '<input type="hidden" name="post_id" value="' . htmlspecialchars($post_data['id']) . '">';

                echo '<div class="mb-3"><label for="title" class="form-label">Title *</label><input type="text" class="form-control" id="title" name="title" value="' . htmlspecialchars($post_data['title']) . '" required></div>';
                echo '<div class="mb-3"><label for="description" class="form-label">Description</label><textarea class="form-control" id="description" name="description" rows="5">' . htmlspecialchars($post_data['description']) . '</textarea></div>';
                echo '<div class="mb-3"><label for="image_url" class="form-label">Image URL *</label><input type="url" class="form-control" id="image_url" name="image_url" value="' . htmlspecialchars($post_data['image_url']) . '" required></div>';
                echo '<hr><h5 class="mt-4">Download Links (Optional)</h5>';
                echo '<div class="mb-3"><label for="link_480p" class="form-label">480p Link</label><input type="url" class="form-control" id="link_480p" name="link_480p" value="' . htmlspecialchars($post_data['link_480p']) . '"></div>';
                echo '<div class="mb-3"><label for="link_720p" class="form-label">720p Link</label><input type="url" class="form-control" id="link_720p" name="link_720p" value="' . htmlspecialchars($post_data['link_720p']) . '"></div>';
                echo '<div class="mb-3"><label for="link_1080p" class="form-label">1080p Link</label><input type="url" class="form-control" id="link_1080p" name="link_1080p" value="' . htmlspecialchars($post_data['link_1080p']) . '"></div>';

                echo '<button type="submit" class="btn btn-success">Save Post</button> ';
                echo '<a href="?action=manage_posts" class="btn btn-secondary">Cancel</a>';
                echo '</form>';
            }

            // -- Manage Posts View --
            elseif ($action === 'manage_posts') {
                echo '<h2>Manage Posts</h2>';
                $sql = "SELECT id, title, view_count, created_at FROM posts ORDER BY created_at DESC";
                $result = $conn->query($sql);
                if ($result && $result->num_rows > 0) {
                    echo '<div class="table-responsive"><table class="table table-striped table-hover">';
                    echo '<thead><tr><th>ID</th><th>Title</th><th>Views</th><th>Created</th><th>Actions</th></tr></thead><tbody>';
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['title']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['view_count']) . '</td>';
                        echo '<td>' . htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))) . '</td>';
                        echo '<td>';
                        echo '<a href="?action=edit_post&id=' . $row['id'] . '" class="btn btn-sm btn-primary me-1">Edit</a>';
                        echo '<a href="?action=delete_post&id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this post?\');">Delete</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="alert alert-info">No posts found. <a href="?action=add_post">Add one?</a></div>';
                }
                 if($result) $result->free();
            }

            // -- Manage Comments View --
             elseif ($action === 'manage_comments') {
                echo '<h2>Manage Comments</h2>';
                 $sql = "SELECT c.id, c.username, c.comment, c.created_at, p.title as post_title, p.id as post_id
                         FROM comments c
                         JOIN posts p ON c.post_id = p.id
                         ORDER BY c.created_at DESC LIMIT 100"; // Limit for performance
                $result = $conn->query($sql);
                 if ($result && $result->num_rows > 0) {
                    echo '<div class="table-responsive"><table class="table table-striped table-hover">';
                    echo '<thead><tr><th>ID</th><th>Post</th><th>User</th><th>Comment</th><th>Date</th><th>Action</th></tr></thead><tbody>';
                    while ($row = $result->fetch_assoc()) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($row['id']) . '</td>';
                        echo '<td><a href="?action=edit_post&id='.$row['post_id'].'" title="Edit Post">' . htmlspecialchars(substr($row['post_title'], 0, 30)). (strlen($row['post_title']) > 30 ? '...' : '') . '</a></td>';
                        echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                        echo '<td>' . htmlspecialchars(substr($row['comment'], 0, 50)) . (strlen($row['comment']) > 50 ? '...' : '') . '</td>';
                        echo '<td>' . htmlspecialchars(date('Y-m-d H:i', strtotime($row['created_at']))) . '</td>';
                        echo '<td>';
                        echo '<a href="?action=delete_comment&id=' . $row['id'] . '" class="btn btn-sm btn-danger" onclick="return confirm(\'Are you sure you want to delete this comment?\');">Delete</a>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    echo '</tbody></table></div>';
                } else {
                    echo '<div class="alert alert-info">No comments found.</div>';
                }
                 if($result) $result->free();
            }

            // Default message if action is not recognized
            elseif($action !== 'dashboard') {
                 echo '<div class="alert alert-warning">Invalid action specified.</div>';
                 echo '<a href="?action=dashboard" class="btn btn-primary">Go to Dashboard</a>';
            }

            ?>
        </main>
    </div>
</div>

<?php endif; // End of admin dashboard section ?>

<?php $conn->close(); // Close DB connection at the end ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
