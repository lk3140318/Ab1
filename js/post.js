document.addEventListener('DOMContentLoaded', () => {
    const postContent = document.getElementById('post-content');
    const loadingIndicator = document.getElementById('loading-post');
    const postTitleEl = document.getElementById('post-title');
    const postBannerEl = document.getElementById('post-banner');
    const postDescriptionEl = document.getElementById('post-description');
    const downloadButtonsContainer = document.getElementById('download-buttons');
    const viewCountEl = document.getElementById('view-count');
    const postDateEl = document.getElementById('post-date');
    const commentsListEl = document.getElementById('comments-list');
    const loadingCommentsEl = document.getElementById('loading-comments');
    const commentCountEl = document.getElementById('comment-count');
    const commentForm = document.getElementById('comment-form');
    const commentUsernameInput = document.getElementById('comment-username');
    const commentTextInput = document.getElementById('comment-text');
    const commentMessageEl = document.getElementById('comment-message');
    const submitCommentBtn = document.getElementById('submit-comment-btn');
    const postIdField = document.getElementById('post-id-field');


    // IMPORTANT: Replace with your actual backend API URL
    const API_BASE_URL = 'https://your-backend-url.koyeb.app/api'; // NO trailing slash
    // Or for local testing: const API_BASE_URL = 'http://localhost/your_backend_folder/api';


    const urlParams = new URLSearchParams(window.location.search);
    const postId = urlParams.get('id');

    if (!postId || isNaN(parseInt(postId))) {
        showError('Invalid post ID in URL.');
        return;
    }

    async function loadPostData() {
        try {
            // 1. Fetch Post Details
            const postResponse = await fetch(`${API_BASE_URL}/post.php?id=${postId}`);
            if (!postResponse.ok) {
                 if (postResponse.status === 404) throw new Error('Post not found.');
                throw new Error(`HTTP error fetching post! status: ${postResponse.status}`);
            }
            const post = await postResponse.json();

            // 2. Increment View Count (fire and forget)
            incrementViewCount(postId);

            // 3. Populate Post Details
            displayPostDetails(post);

             // 4. Fetch Comments
            await loadComments(postId); // Make this async to wait for comments before hiding loader fully


            // 5. Show content, hide loader
            loadingIndicator.style.display = 'none';
            postContent.classList.remove('d-none');

        } catch (error) {
            console.error("Failed to load post data:", error);
            showError(error.message || 'Failed to load post. Please try again.');
        }
    }

     async function loadComments(currentPostId) {
        loadingCommentsEl.style.display = 'block';
        commentsListEl.innerHTML = ''; // Clear existing (except loading indicator initially)
        commentsListEl.appendChild(loadingCommentsEl); // Ensure loading text is visible

        try {
            const commentsResponse = await fetch(`${API_BASE_URL}/comments.php?post_id=${currentPostId}`);
             if (!commentsResponse.ok) {
                throw new Error(`HTTP error fetching comments! status: ${commentsResponse.status}`);
            }
            const comments = await commentsResponse.json();
            displayComments(comments);

        } catch (error) {
             console.error("Failed to load comments:", error);
             loadingCommentsEl.textContent = 'Failed to load comments.';
             commentCountEl.textContent = 'Error';
        }
    }

    function displayPostDetails(post) {
        document.title = `${escapeHtml(post.title)} - Movie/Series Hub`; // Update page title
        postTitleEl.textContent = escapeHtml(post.title);
        postBannerEl.src = sanitizeUrl(post.image_url);
        postBannerEl.alt = escapeHtml(post.title) + " Banner";
        postBannerEl.onerror = () => {
            postBannerEl.onerror = null; // Prevent infinite loop if placeholder fails
            postBannerEl.src = 'https://via.placeholder.com/800x400?text=Image+Not+Available';
        };
        postDescriptionEl.innerHTML = nl2br(escapeHtml(post.description || 'No description available.')); // Use innerHTML for nl2br
        viewCountEl.textContent = post.view_count !== undefined ? post.view_count : 'N/A';
        postDateEl.textContent = post.created_at ? formatDate(post.created_at) : 'N/A';
        postIdField.value = post.id; // Set hidden field for comment form

        // Populate Download Buttons
        downloadButtonsContainer.innerHTML = ''; // Clear existing
        let buttonsAdded = 0;
        if (post.link_1080p) {
            downloadButtonsContainer.innerHTML += `<a href="${sanitizeUrl(post.link_1080p)}" class="btn btn-warning fw-bold me-md-2 mb-2 mb-md-0" target="_blank" rel="noopener noreferrer">Download 1080p</a>`;
            buttonsAdded++;
        }
         if (post.link_720p) {
             downloadButtonsContainer.innerHTML += `<a href="${sanitizeUrl(post.link_720p)}" class="btn btn-outline-secondary me-md-2 mb-2 mb-md-0" target="_blank" rel="noopener noreferrer">Download 720p</a>`;
             buttonsAdded++;
        }
         if (post.link_480p) {
             downloadButtonsContainer.innerHTML += `<a href="${sanitizeUrl(post.link_480p)}" class="btn btn-outline-secondary mb-2 mb-md-0" target="_blank" rel="noopener noreferrer">Download 480p</a>`;
              buttonsAdded++;
        }
        if (buttonsAdded === 0) {
             downloadButtonsContainer.innerHTML = '<p class="text-muted mb-0">No download links available for this post.</p>';
        }
    }

    function displayComments(comments) {
        commentsListEl.innerHTML = ''; // Clear loading indicator or previous comments
         commentCountEl.textContent = comments.length;

        if (comments.length === 0) {
            commentsListEl.innerHTML = '<p class="text-muted">Be the first to comment!</p>';
            return;
        }

        comments.forEach(comment => {
            const commentDiv = document.createElement('div');
            commentDiv.className = 'comment mb-3 pb-3 border-bottom';
            commentDiv.innerHTML = `
                <p class="mb-1">${nl2br(escapeHtml(comment.comment))}</p>
                <small class="text-muted comment-meta">
                    By <strong>${escapeHtml(comment.username)}</strong> on ${formatDate(comment.created_at)}
                </small>
            `;
            commentsListEl.appendChild(commentDiv);
        });
    }

     async function incrementViewCount(currentPostId) {
        try {
            await fetch(`${API_BASE_URL}/view.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: currentPostId }),
            });
            // No need to handle response unless debugging needed
        } catch (error) {
            console.warn("Could not increment view count:", error);
            // Don't block user experience for this
        }
    }

    async function handleCommentSubmit(event) {
        event.preventDefault();
        commentMessageEl.textContent = '';
        commentMessageEl.className = 'mt-3'; // Reset classes
        submitCommentBtn.disabled = true;
        submitCommentBtn.textContent = 'Submitting...';

        const username = commentUsernameInput.value.trim();
        const commentText = commentTextInput.value.trim();
        const currentPostId = postIdField.value;

        if (!username || !commentText) {
            showCommentMessage('danger', 'Name and comment cannot be empty.');
            submitCommentBtn.disabled = false;
            submitCommentBtn.textContent = 'Submit Comment';
            return;
        }

        try {
            const response = await fetch(`${API_BASE_URL}/comments.php`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    post_id: parseInt(currentPostId),
                    username: username,
                    comment: commentText
                }),
            });

            const result = await response.json();

            if (!response.ok || !result.success) {
                 throw new Error(result.error || `HTTP error! status: ${response.status}`);
            }

            // Success!
            showCommentMessage('success', 'Comment submitted successfully!');
            commentForm.reset(); // Clear the form

            // Add the new comment to the top of the list immediately
             if (result.comment) {
                const commentDiv = document.createElement('div');
                commentDiv.className = 'comment mb-3 pb-3 border-bottom';
                 commentDiv.style.backgroundColor = '#e9f5ff'; // Highlight new comment briefly
                 commentDiv.innerHTML = `
                    <p class="mb-1">${nl2br(escapeHtml(result.comment.comment))}</p>
                    <small class="text-muted comment-meta">
                        By <strong>${escapeHtml(result.comment.username)}</strong> on ${formatDate(result.comment.created_at)}
                    </small>
                `;
                // Remove "no comments" message if it exists
                const noCommentsMsg = commentsListEl.querySelector('p.text-muted');
                if(noCommentsMsg && noCommentsMsg.textContent === 'Be the first to comment!') {
                    commentsListEl.innerHTML = '';
                }
                commentsListEl.prepend(commentDiv); // Add to the top
                commentCountEl.textContent = parseInt(commentCountEl.textContent) + 1;
                 // Remove highlight after a delay
                 setTimeout(() => { commentDiv.style.backgroundColor = ''; }, 3000);
            } else {
                // Fallback: Reload comments if new comment data wasn't returned
                await loadComments(currentPostId);
            }


        } catch (error) {
            console.error("Failed to submit comment:", error);
            showCommentMessage('danger', `Error: ${error.message || 'Could not submit comment.'}`);
        } finally {
            submitCommentBtn.disabled = false;
            submitCommentBtn.textContent = 'Submit Comment';
        }
    }

    function showCommentMessage(type, message) {
         commentMessageEl.className = `mt-3 alert alert-${type}`;
         commentMessageEl.textContent = message;
    }

    function showError(message) {
         loadingIndicator.style.display = 'none'; // Hide spinner
         postContent.innerHTML = `<div class="alert alert-danger">${escapeHtml(message)} <a href="index.html">Go back home</a></div>`;
         postContent.classList.remove('d-none'); // Show the error message container
    }


    // --- Utility Functions ---
    function escapeHtml(unsafe) {
         if (!unsafe) return '';
        return unsafe
             .replace(/&/g, "&")
             .replace(/</g, "<")
             .replace(/>/g, ">")
             .replace(/"/g, """)
             .replace(/'/g, "'");
    }

     function sanitizeUrl(url) {
        if (!url || typeof url !== 'string') return '#';
        const trimmedUrl = url.trim();
         if (trimmedUrl.startsWith('http://') || trimmedUrl.startsWith('https://') || trimmedUrl.startsWith('magnet:')) { // Allow magnet links too
             return escapeHtml(trimmedUrl);
         }
         console.warn("Invalid URL sanitized:", url);
         return '#';
    }


    function nl2br(str) {
        if (typeof str === 'undefined' || str === null) {
            return '';
        }
        return (str + '').replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br>$2');
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        try {
            const date = new Date(dateString);
             // Check if the date is valid
             if (isNaN(date.getTime())) {
                return dateString; // Return original string if parsing failed
             }
             // Format as desired, e.g., "Jan 15, 2023, 10:30 AM"
             return date.toLocaleDateString(undefined, {
                 year: 'numeric', month: 'short', day: 'numeric',
                 //hour: 'numeric', minute: '2-digit', hour12: true // Optional: Add time
             });
        } catch (e) {
            console.error("Error formatting date:", dateString, e);
            return dateString; // Return original string on error
        }
    }

    // --- Event Listeners ---
    commentForm.addEventListener('submit', handleCommentSubmit);

    // --- Initial Load ---
    loadPostData();
});
