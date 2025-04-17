document.addEventListener('DOMContentLoaded', () => {
    const postGrid = document.getElementById('post-grid');
    const loadingIndicator = document.getElementById('loading');

    // IMPORTANT: Replace with your actual backend API URL
    const API_BASE_URL = 'https://your-backend-url.koyeb.app/api'; // NO trailing slash
    // Or for local testing: const API_BASE_URL = 'http://localhost/your_backend_folder/api';

    async function fetchPosts() {
        try {
            const response = await fetch(`${API_BASE_URL}/posts.php`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const posts = await response.json();

            loadingIndicator.style.display = 'none'; // Hide loading indicator
            displayPosts(posts);

        } catch (error) {
            console.error("Failed to fetch posts:", error);
            loadingIndicator.innerHTML = '<p class="text-danger">Failed to load posts. Please try refreshing the page.</p>';
        }
    }

    function displayPosts(posts) {
        postGrid.innerHTML = ''; // Clear previous content or loading indicator inside grid

        if (!posts || posts.length === 0) {
            postGrid.innerHTML = '<p class="col-12 text-center text-muted">No posts found.</p>';
            return;
        }

        posts.forEach(post => {
            const col = document.createElement('div');
            col.className = 'col';

            // Basic check if any download link exists
            const hasDownloads = post.link_1080p || post.link_720p || post.link_480p;

            let downloadButtonsHtml = '';
            if (hasDownloads) {
                if (post.link_1080p) {
                    downloadButtonsHtml += `<a href="${sanitizeUrl(post.link_1080p)}" class="btn btn-sm btn-warning fw-bold mb-1" target="_blank" rel="noopener noreferrer">Download 1080p</a>`;
                }
                 if (post.link_720p) {
                    downloadButtonsHtml += `<a href="${sanitizeUrl(post.link_720p)}" class="btn btn-sm btn-outline-secondary mb-1" target="_blank" rel="noopener noreferrer">Download 720p</a>`;
                }
                 if (post.link_480p) {
                    downloadButtonsHtml += `<a href="${sanitizeUrl(post.link_480p)}" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener noreferrer">Download 480p</a>`;
                }
            } else {
                downloadButtonsHtml = '<p class="text-muted small mb-0">No download links available.</p>';
            }


            col.innerHTML = `
                <div class="card h-100 shadow-sm">
                    <a href="post.html?id=${post.id}">
                       <img src="${sanitizeUrl(post.image_url)}" class="card-img-top" alt="${escapeHtml(post.title)}" onerror="this.onerror=null;this.src='https://via.placeholder.com/300x450?text=Image+Error';">
                    </a>
                    <div class="card-body">
                        <h5 class="card-title mb-0">
                            <a href="post.html?id=${post.id}" class="text-decoration-none text-dark stretched-link">
                                ${escapeHtml(post.title)}
                            </a>
                        </h5>
                    </div>
                     ${hasDownloads ? `
                    <div class="card-footer bg-white border-top-0 pb-3">
                         <div class="d-grid gap-1">
                            ${downloadButtonsHtml}
                        </div>
                    </div>` : ''}
                </div>
            `;
            postGrid.appendChild(col);
        });
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
        // Basic URL sanitization (allow http, https) - Enhance if needed
        if (!url || typeof url !== 'string') return '#'; // Default to '#' if invalid
        const trimmedUrl = url.trim();
        if (trimmedUrl.startsWith('http://') || trimmedUrl.startsWith('https://')) {
             // Could add more validation here (e.g., regex)
             return escapeHtml(trimmedUrl); // Escape potential XSS in the URL itself
        }
        // If it's not a valid absolute URL, return '#' or handle as appropriate
        console.warn("Invalid URL provided:", url);
        return '#'; // Or perhaps log an error and return a default placeholder URL
    }


    // --- Initial Load ---
    fetchPosts();
});
