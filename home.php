<?php
// student_dashboard.php

// --- Add Cache-Control Headers to prevent caching ---
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
// --- End Cache-Control Headers ---

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'db_connect.php';

// Define which user types are allowed to access this page
$allowed_roles = ['student', 'admin','staff'];

if (!isset($_SESSION['user_id']) || !in_array(strtolower($_SESSION['usertype']), $allowed_roles)) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// --- Fetch All Published Content with Upvote and Comment Counts ---
$published_content_raw = [];
$stmt_published = $conn->prepare("
    SELECT
        tc.content_id, tc.title, tc.contentbody, tc.file_path, tc.published_at,
        (SELECT COUNT(*) FROM tbl_feedback WHERE content_id = tc.content_id AND upvoted = 1) AS upvotes,
        (SELECT COUNT(*) FROM tbl_feedback WHERE content_id = tc.content_id AND comment IS NOT NULL AND comment != '') AS comments_count,
        (SELECT MAX(upvoted) FROM tbl_feedback WHERE content_id = tc.content_id AND student_id = ?) AS my_upvote_status,
        ts.student_name, tct.type_name AS content_type
    FROM tbl_content tc
    JOIN tbl_content_approval tca ON tc.content_id = tca.content_id
    JOIN tbl_student ts ON tc.student_id = ts.student_id
    JOIN tbl_content_type tct ON tc.type_id = tct.type_id
    WHERE tc.published_at IS NOT NULL AND tc.published_at <= NOW() AND tca.status = 'approved' AND tc.is_deleted = FALSE
    ORDER BY tc.published_at DESC
");
$stmt_published->bind_param("i", $student_id);
$stmt_published->execute();
$result_published = $stmt_published->get_result();
while ($row = $result_published->fetch_assoc()) {
    $published_content_raw[] = $row;
}
$stmt_published->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creative Voices Magazine</title>
    <link rel="stylesheet" href="css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Roboto:wght@300;400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    
    <style>
        body.magazine-open {
            overflow-y: auto;
        }
        .main-content {
            flex-grow: 1; padding: 2rem; box-sizing: border-box; margin: 0;
            display: flex; justify-content: center; align-items: center;
            overflow: hidden; position: relative; background-color: transparent; box-shadow: none;
        }
        .magazine-cover-container {
            width: 100%; height: 100%; display: flex; justify-content: center; align-items: center;
            position: absolute; top: 0; left: 0;
            transition: opacity 0.8s ease-in-out, transform 0.8s ease-in-out;
            perspective: 1500px; z-index: 100;
        }
        .magazine-cover {
             width: 100%; max-width: 800px; aspect-ratio: 16 / 9; position: relative;
             overflow: hidden; border-radius: 15px; box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
             display: flex; flex-direction: column; justify-content: flex-end;
            text-align: center; color: white; border: 5px solid rgba(255,255,255,0.3);
             animation: fadeInText 1.5s ease-out;
     transform: translateZ(75px);

         }
        .magazine-cover video {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            object-fit: cover; z-index: 1; filter: brightness(0.8);
        }
        .cover-overlay {
            background: linear-gradient(to top, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 60%);
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            display: flex; flex-direction: column; justify-content: flex-end;
            align-items: center; padding-bottom: 40px; z-index: 2;
            transform: translateZ(20px);
        }
        .magazine-cover h1 { font-family: 'Playfair Display', serif; font-size: 3.5em; color: #fff; margin-bottom: 15px; }
        .magazine-cover p { font-family: 'Roboto', sans-serif; font-size: 1.2em; margin-bottom: 30px; color: #f0f0f0;  animation: fadeInText 1.5s ease-out 0.5s forwards;
         opacity: 0; /* Required for the animation to work */
         transform: translateZ(50px); }
        .open-magazine-btn {
            position: relative; overflow: hidden;
            background: linear-gradient(45deg, var(--color-primary-2, #33CDB9), var(--color-primary, #00BFA5));
            color: var(--color-bg, #F8F9FA); padding: 18px 35px; border: 1px solid rgba(0, 191, 165, 0.35);
            border-radius: 50px; font-size: 1.1em; font-weight: 700; cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, filter 0.2s ease;
            transform: translateZ(60px);
            box-shadow: 0 10px 24px rgba(0, 191, 165, 0.35);
            will-change: transform;
        }
        .open-magazine-btn:hover { transform: translateY(-3px) translateZ(60px) scale(1.02); filter: brightness(1.05); box-shadow: 0 16px 34px rgba(0, 191, 165, 0.45); }
        .open-magazine-btn:active { transform: translateY(0) translateZ(60px) scale(0.98); }
        .open-magazine-btn:focus-visible { outline: 2px solid var(--color-primary-2, #33CDB9); outline-offset: 4px; }
        .open-magazine-btn::after { content: ''; position: absolute; top: -150%; left: -50%; width: 50%; height: 400%;
            background: linear-gradient(120deg, transparent, rgba(255,255,255,0.35), transparent); transform: rotate(25deg);
            transition: transform 0.6s ease; pointer-events: none; }
        .open-magazine-btn:hover::after { transform: translateX(300%) rotate(25deg); }
        .open-magazine-btn .ripple { position: absolute; border-radius: 50%; transform: scale(0); background: rgba(255,255,255,0.6);
            animation: ripple 700ms ease-out; pointer-events: none; mix-blend-mode: screen; }
        @keyframes ripple { to { transform: scale(4); opacity: 0; } }
        .magazine-cover-container.open {
            opacity: 0; transform: scale(1.2); pointer-events: none;
        }

        /* --- Grid Theme (Light Gray + Teal + Orange) --- */
        .magazine-grid-container {
            width: 100%; height: 100%; padding: 20px; box-sizing: border-box;
            overflow-y: auto; position: absolute; top: 0; left: 0;
            background: radial-gradient(1200px 600px at 20% 10%, rgba(0, 191, 165, 0.08), transparent 55%),
                        radial-gradient(900px 500px at 80% 20%, rgba(255, 140, 66, 0.06), transparent 60%),
                        linear-gradient(180deg, var(--color-bg, #F8F9FA) 0%, var(--color-surface, #FFFFFF) 100%);
            color: var(--color-text, #2B3A42);
        }
        .controls-header {
            display: flex; justify-content: center; align-items: center; padding: 16px 20px;
            margin-bottom: 20px; border-radius: 14px;
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.85), rgba(245, 245, 245, 0.85));
            border: 1px solid rgba(0, 191, 165, 0.18);
            box-shadow: 0 8px 24px rgba(0,0,0,0.1), 0 0 0 1px rgba(0,191,165,0.08) inset;
            backdrop-filter: blur(6px);
            position: sticky; top: 0; z-index: 50;
        }
        .sort-options { display: flex; align-items: center; gap: 12px; color: var(--color-muted, #4B5C66); }
        .btn-sort { background: linear-gradient(45deg, var(--color-primary-2, #33CDB9), var(--color-primary, #00BFA5)); color: var(--color-bg, #F8F9FA); padding: 10px 20px; border-radius: 999px; text-decoration: none; font-weight: 700; transition: transform 0.2s ease, box-shadow 0.2s ease; border: 1px solid rgba(0, 191, 165, 0.25); cursor: pointer; box-shadow: 0 8px 20px rgba(0, 191, 165, 0.25); }
        .btn-sort:hover { transform: translateY(-2px); box-shadow: 0 12px 26px rgba(0, 191, 165, 0.35); }
        .btn-sort.active { background: linear-gradient(45deg, var(--color-primary, #00BFA5), var(--color-primary-3, #009985)); color: var(--color-bg, #F8F9FA); box-shadow: 0 12px 28px rgba(0, 191, 165, 0.4); }
        
        .content-grid {
            column-count: 4; /* Default number of columns */
            column-gap: 25px;
            padding: 20px;
        }
        
        .content-tile {
            background: linear-gradient(180deg, var(--color-surface, #FFFFFF), var(--color-bg, #F8F9FA));
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(0,0,0,0.1);
            border: 1px solid rgba(0,191,165,0.14);
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
            margin-bottom: 25px;
            break-inside: avoid;
            width: 100%;
            display: inline-block;
            position: relative;
        }
        .content-tile:hover { transform: translateY(-8px); box-shadow: 0 16px 36px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,191,165,0.18) inset; border-color: rgba(0,191,165,0.22); }
        .tile-media { width: 100%; display: block; object-fit: cover; }
        .tile-media + .tile-content { border-top: 1px solid rgba(0,191,165,0.12); }
        .tile-content { padding: 16px; }
        .tile-content h3 { font-family: 'Poppins', 'Inter', system-ui, -apple-system, Arial, sans-serif; font-size: 1.2em; margin-bottom: 8px; color: var(--color-text, #2B3A42); }
        .tile-content p { font-size: 0.95em; color: var(--color-muted, #4B5C66); margin-bottom: 15px; }
        .tile-actions { display: flex; justify-content: space-between; align-items: center; }
        .btn-action { border: none; cursor: pointer; color: var(--color-bg, #F8F9FA); font-size: 0.95em; display: flex; align-items: center; gap: 6px; padding: 8px 12px; border-radius: 999px; transition: transform 0.2s ease, box-shadow 0.2s ease; background: linear-gradient(45deg, var(--color-primary-2, #33CDB9), var(--color-primary, #00BFA5)); box-shadow: 0 6px 16px rgba(0,191,165,0.25); }
        .btn-action:hover { transform: translateY(-2px); box-shadow: 0 10px 22px rgba(0,191,165,0.35); }
        .upvote-btn.upvoted { filter: saturate(1.2); box-shadow: 0 0 0 2px rgba(0,0,0,0.1) inset, 0 8px 18px rgba(255,140,66,0.35); }

        /* Responsive columns for masonry layout */
        @media (max-width: 1200px) { .content-grid { column-count: 3; } }
        @media (max-width: 992px) { .content-grid { column-count: 2; } }
        @media (max-width: 768px) { .content-grid { column-count: 1; } }

        /* --- MODAL AND COMMENT STYLES --- */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.7); z-index: 1002; justify-content: center; align-items: center; }
        .modal-content { background: linear-gradient(180deg, var(--color-surface, #FFFFFF), var(--color-bg, #F8F9FA)); color: var(--color-text, #2B3A42); border-radius: 14px; width: 90%; max-width: 800px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 18px 42px rgba(0,0,0,0.15); position: relative; border: 1px solid rgba(0,191,165,0.2); }
        .modal-header { padding: 20px; border-bottom: 1px solid rgba(0,191,165,0.14); }
        .modal-header h2 { font-family: 'Poppins', 'Inter', system-ui, -apple-system, Arial, sans-serif; }
        .modal-header .author-info { font-style: italic; color: var(--color-muted, #4B5C66); font-size: 0.9em; }
        .close-button { position: absolute; top: 15px; right: 20px; font-size: 28px; font-weight: bold; cursor: pointer; color: #999999; transition: color 0.2s ease, transform 0.2s ease; }
        .close-button:hover { color: var(--color-primary, #00BFA5); transform: scale(1.05); }
        .modal-body { overflow-y: auto; padding: 20px; }
        .modal-body img, .modal-body video { max-width: 100%; border-radius: 8px; }
        .modal-body .content-text { white-space: pre-wrap; word-wrap: break-word; line-height: 1.7; }/* --- Styles for Comment Section --- */
.comments-section {
    margin-top: 20px;
    border-top: 1px solid #E0E0E0;
    padding-top: 20px;
}
.comments-section h4 {
    margin-bottom: 15px;
}
.comments-list .comment-item {
    border-bottom: 1px solid #F0F0F0;
    padding: 15px 0;
}
.comments-list .comment-item:last-child {
    border-bottom: none;
}
.comment-date {
    font-size: 0.8em;
    color: #777;
    margin-left: 5px;
}
.comment-text-display {
    margin-top: 5px;
}
.comment-actions {
    margin-top: 8px;
}
.comment-actions button {
    font-size: 0.85em;
    margin-right: 10px;
    background: rgba(255,107,107,0.08);
    border: 1px solid rgba(255,107,107,0.18);
    cursor: pointer;
    color: var(--color-text, #333333);
    padding: 6px 10px;
    border-radius: 8px;
    transition: background-color 0.2s ease, transform 0.2s ease;
}
.comment-actions button:hover { background: rgba(255,107,107,0.14); transform: translateY(-1px); }
.add-comment-form, .edit-comment-form {
    margin-top: 20px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.add-comment-form textarea, .edit-comment-form textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #E0E0E0;
    background: #FEFDFB;
    color: var(--color-text, #333333);
    border-radius: 10px;
    resize: vertical;
    min-height: 60px;
    font-family: inherit;
    transition: box-shadow 0.2s ease, border-color 0.2s ease;
}
.add-comment-form textarea:focus, .edit-comment-form textarea:focus {
    border-color: var(--color-primary, #FF6B6B);
    box-shadow: 0 0 0 3px rgba(255, 107, 107, 0.2);
    outline: none;
}
.add-comment-form button, .edit-comment-form button {
    padding: 10px 20px;
    border-radius: 999px;
    border: 1px solid rgba(255,107,107,0.25);
    background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B));
    color: #FEFDFB;
    cursor: pointer;
    align-self: flex-end;
    font-weight: 700;
    box-shadow: 0 8px 20px rgba(255,107,107,0.25);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.add-comment-form button:hover, .edit-comment-form button:hover { transform: translateY(-1px); box-shadow: 0 12px 28px rgba(255,107,107,0.35); }
.edit-comment-form .cancel-edit-btn {
    background: linear-gradient(45deg, #E0E0E0, #D0D0D0);
    color: var(--color-text, #333333);
    border: 1px solid #C0C0C0;
    margin-left: auto;
    margin-right: 10px;
}
        .comments-section { margin-top: 20px; }
        .comments-section h4 { margin-bottom: 15px; }
        .comments-list .comment-item { border-bottom: 1px solid #E0E0E0; padding: 10px 0; }
        .comments-list .comment-item:last-child { border-bottom: none; }
        .add-comment-form { margin-top: 20px; display: flex; gap: 10px; }
        .add-comment-form textarea { flex-grow: 1; padding: 10px; border: 1px solid #E0E0E0; border-radius: 8px; resize: vertical; min-height: 40px; }
        .add-comment-form button { padding: 10px 20px; border-radius: 8px; border: none; background: linear-gradient(45deg, var(--color-primary-2, #FF8E8E), var(--color-primary, #FF6B6B)); color: #FEFDFB; cursor: pointer; }
        .comment-actions { margin-top: 5px; }
        .comment-actions button { font-size: 0.8em; margin-right: 10px; background: none; border: none; cursor: pointer; color: #666; }
   @keyframes fadeInText {
    from { 
        opacity: 0; 
        transform: translateY(20px) translateZ(50px); 
    }
    to { 
        opacity: 1; 
        transform: translateY(0) translateZ(50px); 
    }
}
   </style>
</head>
<body class="magazine-closed">
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <div id="magazineCoverContainer" class="magazine-cover-container">
                <div class="magazine-cover">
                    <video autoplay loop muted playsinline poster="cover-poster.png"><source src="cover-video.mp4" type="video/mp4"></video>
                    <div class="cover-overlay">
                        <h1>The Creative Voices Magazine 2025</h1>
                        <p>Explore a world of creativity from our students!</p>
                        <button class="open-magazine-btn">Explore</button>
                    </div>
                </div>
            </div>
            <div id="magazineGridContainer" class="magazine-grid-container" style="display: none;">
                <header class="controls-header">
                    <div class="sort-options">
                        <span>Sort by:</span>
                        <button id="sortLatestBtn" class="btn-sort active">Latest</button>
                        <button id="sortUpvotesBtn" class="btn-sort">Most Upvoted</button>
                    </div>
                </header>
                <div id="contentGrid" class="content-grid"></div>
            </div>
        </div>
    </div>
    <div id="fullContentModal" class="modal-overlay">
        <div class="modal-content">
            <span class="close-button">&times;</span>
            <div class="modal-header">
                <h2 id="modalTitle"></h2>
                <p id="modalAuthor" class="author-info"></p>
            </div>
            <div id="modalContentBody" class="modal-body"></div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // --- Variables ---
        const currentStudentId = <?php echo json_encode($student_id); ?>;
        let allPublishedContent = <?php echo json_encode($published_content_raw); ?>;
        const contentGrid = document.getElementById('contentGrid');
        const body = document.body;
        const magazineCoverContainer = document.getElementById('magazineCoverContainer');
        const openMagazineBtn = document.querySelector('.open-magazine-btn');
        const magazineGridContainer = document.getElementById('magazineGridContainer');
        const sortLatestBtn = document.getElementById('sortLatestBtn');
        const sortUpvotesBtn = document.getElementById('sortUpvotesBtn');
        const fullContentModal = document.getElementById("fullContentModal");
        const modalTitle = document.getElementById("modalTitle");
        const modalAuthor = document.getElementById("modalAuthor");
        const modalContentBody = document.getElementById("modalContentBody");
        const modalCloseButton = fullContentModal.querySelector(".close-button");

        // --- 3D Tilt Animation for Magazine Cover ---
const magazineCover = document.querySelector('.magazine-cover');
if (magazineCover) {
    magazineCoverContainer.addEventListener('mousemove', (e) => {
        const { width, height, left, top } = magazineCoverContainer.getBoundingClientRect();
        const x = e.clientX - left;
        const y = e.clientY - top;
        const mouseX = x - width / 2;
        const mouseY = y - height / 2;
        const rotateY = (20 * mouseX) / (width / 2); // Adjust '20' for more/less tilt
        const rotateX = (-20 * mouseY) / (height / 2); // Adjust '20' for more/less tilt
        
        magazineCover.style.transform = `rotateX(${rotateX}deg) rotateY(${rotateY}deg)`;
    });

    magazineCoverContainer.addEventListener('mouseleave', () => {
        magazineCover.style.transform = 'rotateX(0deg) rotateY(0deg)';
    });
}

        // --- All Functions ---

        // Renders a single tile
        function renderTile(content) {
            const tile = document.createElement('div');
            tile.className = 'content-tile';
            tile.dataset.contentId = content.content_id;
            let mediaPreview = '';
            if (content.content_type.toLowerCase() === 'image' && content.file_path) {
                mediaPreview = `<img src="${content.file_path}" alt="${content.title}" class="tile-media">`;
            } else if (content.content_type.toLowerCase() === 'video' && content.file_path) {
                mediaPreview = `<div style="position: relative;"><video class="tile-media" muted playsinline loop><source src="${content.file_path}#t=0.5" type="video/mp4"></video><i class="fas fa-play" style="position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); color:white; font-size: 2em; text-shadow: 0 0 10px black;"></i></div>`;
            }
            tile.innerHTML = `
                ${mediaPreview}
                <div class="tile-content">
                    <h3>${content.title}</h3>
                    <p>By ${content.student_name}</p>
                    <div class="tile-actions">
                        <button class="btn-action upvote-btn" data-upvoted="${content.my_upvote_status == 1 ? 'true' : 'false'}">
                            <i class="fas fa-thumbs-up"></i> <span class="upvote-count">${content.upvotes}</span>
                        </button>
                        <button class="btn-action comment-btn">
                            <i class="fas fa-comment"></i> <span>${content.comments_count}</span>
                        </button>
                    </div>
                </div>`;
            tile.addEventListener('click', (e) => { if (!e.target.closest('button')) openModal(content); });
            const upvoteBtn = tile.querySelector('.upvote-btn');
            upvoteBtn.addEventListener('click', handleUpvote);
            if (content.my_upvote_status == 1) upvoteBtn.classList.add('upvoted');
            tile.querySelector('.comment-btn').addEventListener('click', () => openModal(content));
            return tile;
        }

        // Renders the entire grid
        function renderGrid(contentArray) {
            contentGrid.innerHTML = '';
            contentArray.forEach(content => {
                contentGrid.appendChild(renderTile(content));
            });
        }

        // Opens the content modal
        function openModal(content) {
    modalTitle.textContent = content.title;
    modalAuthor.textContent = `By ${content.student_name} | Published: ${new Date(content.published_at).toLocaleDateString()}`;
    
    let mediaHtml = '';
    if (content.content_type.toLowerCase() === 'image' && content.file_path) {
        mediaHtml = `<img src="${content.file_path}" alt="${content.title}">`;
    } else if (content.content_type.toLowerCase() === 'video' && content.file_path) {
        mediaHtml = `<video controls autoplay><source src="${content.file_path}" type="video/mp4"></video>`;
    } else {
        mediaHtml = `<div class="content-text">${content.contentbody.replace(/\\r\\n|\\n|\\r/g, '\n')}</div>`;
    }

    modalContentBody.innerHTML = `
        ${mediaHtml}
        <div class="comments-section" data-content-id="${content.content_id}">
            <h4>Comments</h4>
            <div class="comments-list"><p>Loading comments...</p></div>
            <form class="add-comment-form">
                <textarea name="comment_text" placeholder="Add a comment..." required></textarea>
                <button type="submit">Post</button>
            </form>
        </div>
    `;
    
    // This part is crucial for loading comments and activating the form
    fetchComments(content.content_id);
    const commentForm = modalContentBody.querySelector('.add-comment-form');
    commentForm.addEventListener('submit', (e) => {
        e.preventDefault();
        postComment(content.content_id, commentForm);
    });
    
    fullContentModal.style.display = 'flex';
}
        // Handles upvoting
        function handleUpvote(e) {
            const button = e.currentTarget;
            const tile = button.closest('.content-tile');
            const contentId = tile.dataset.contentId;
            const upvoteCountSpan = button.querySelector('.upvote-count');
            let isUpvoted = button.dataset.upvoted === 'true';
            isUpvoted = !isUpvoted;
            button.dataset.upvoted = isUpvoted;
            button.classList.toggle('upvoted', isUpvoted);
            upvoteCountSpan.textContent = parseInt(upvoteCountSpan.textContent) + (isUpvoted ? 1 : -1);
            const contentItem = allPublishedContent.find(c => c.content_id == contentId);
            contentItem.upvotes = upvoteCountSpan.textContent;
            contentItem.my_upvote_status = isUpvoted ? 1 : 0;
            fetch('update_upvote.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `content_id=${contentId}&action=${isUpvoted ? 'upvote' : 'unupvote'}`
            });
        }
        
        // Handles fetching comments
        function fetchComments(contentId) {
            const commentsList = document.querySelector('#fullContentModal .comments-list');
            const currentUserType = '<?php echo strtolower($_SESSION['usertype']); ?>'; // Get current user type

     fetch(`comments_api.php?action=get_comments&content_id=${contentId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.comments.length > 0) {
                commentsList.innerHTML = data.comments.map(comment => {
                    // Determine if the action buttons should be shown
                    const showActions = (comment.student_id == currentStudentId || currentUserType === 'admin' || currentUserType === 'staff');
                    
                    return `
                        <div class="comment-item" data-feedback-id="${comment.feedback_id}" data-student-id="${comment.student_id}">
                            <p><strong>${comment.student_name}</strong> <span class="comment-date">on ${new Date(comment.comment_date).toLocaleDateString()}</span></p>
                            <p class="comment-text-display">${comment.comment_text}</p>
                            ${showActions ? `
                            <div class="comment-actions">
                                ${comment.student_id == currentStudentId ? `<button class="edit-comment-btn">Edit</button>` : ''}
                                <button class="delete-comment-btn">Delete</button>
                            </div>` : ''}
                        </div>
                    `;
                }).join('');
                attachCommentActionListeners(contentId);
            } else {
                commentsList.innerHTML = '<p>No comments yet. Be the first to comment!</p>';
            }
        })
        .catch(err => console.error("Error fetching comments:", err));
}
        
      function postComment(contentId, form, feedbackId = null) {
    const textarea = form.querySelector('textarea');
    const formData = new FormData();
    formData.append('action', feedbackId ? 'edit_comment' : 'add_comment');
    formData.append('content_id', contentId);
    formData.append('comment_text', textarea.value);
    if (feedbackId) {
        formData.append('feedback_id', feedbackId);
    }

    fetch('comments_api.php', { method: 'POST', body: new URLSearchParams(formData) })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
        textarea.value = '';
            fetchComments(contentId); // Refresh the comments list
            const tile = contentGrid.querySelector(`.content-tile[data-content-id='${contentId}']`);
            if (tile) {
                tile.querySelector('.comment-btn span').textContent = data.new_comment_count;
            }
        } else {
            alert(data.message || "Failed to post comment.");
        }
    })
    .catch(err => console.error("Error posting comment:", err));
}

function deleteComment(contentId, feedbackId) {
    if (!confirm("Are you sure you want to delete this comment?")) return;

    const formData = new FormData();
    formData.append('action', 'delete_comment');
    formData.append('content_id', contentId);
    formData.append('feedback_id', feedbackId);
    
    fetch('comments_api.php', { method: 'POST', body: new URLSearchParams(formData) })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            fetchComments(contentId);
            const tile = contentGrid.querySelector(`.content-tile[data-content-id='${contentId}']`);
            if (tile) {
                tile.querySelector('.comment-btn span').textContent = data.new_comment_count;
            }
        } else {
            alert(data.message || "Failed to delete comment.");
        }
    })
    .catch(err => console.error("Error deleting comment:", err));
}

function attachCommentActionListeners(contentId) {
    document.querySelectorAll('#fullContentModal .edit-comment-btn').forEach(button => {
        button.onclick = (e) => {
            const commentItem = e.target.closest('.comment-item');
            const feedbackId = commentItem.dataset.feedbackId;
            const currentText = commentItem.querySelector('.comment-text-display').textContent;
            
            commentItem.innerHTML = `
                <form class="edit-comment-form">
                    <textarea name="comment_text" required>${currentText}</textarea>
                    <div>
                        <button type="submit">Save</button>
                        <button type="button" class="cancel-edit-btn">Cancel</button>
                    </div>
                </form>
            `;
            
            const editForm = commentItem.querySelector('.edit-comment-form');
            editForm.addEventListener('submit', (ev) => {
                ev.preventDefault();
                postComment(contentId, editForm, feedbackId);
            });
            commentItem.querySelector('.cancel-edit-btn').addEventListener('click', () => fetchComments(contentId));
        };
    });

    document.querySelectorAll('#fullContentModal .delete-comment-btn').forEach(button => {
        button.onclick = (e) => {
            const commentItem = e.target.closest('.comment-item');
            const feedbackId = commentItem.dataset.feedbackId;
            deleteComment(contentId, feedbackId);
        };
    });
}

        // --- Event Listeners ---
        function spawnRipple(e, el) {
            const rect = el.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height) * 1.5;
            const ripple = document.createElement('span');
            ripple.className = 'ripple';
            ripple.style.width = ripple.style.height = `${size}px`;
            const x = (e.clientX || (rect.left + rect.width / 2)) - rect.left - size / 2;
            const y = (e.clientY || (rect.top + rect.height / 2)) - rect.top - size / 2;
            ripple.style.left = `${x}px`;
            ripple.style.top = `${y}px`;
            el.appendChild(ripple);
            setTimeout(() => ripple.remove(), 700);
        }

        openMagazineBtn.addEventListener('click', (e) => {
            spawnRipple(e, openMagazineBtn);
            if (allPublishedContent.length === 0) { alert('No content to display yet!'); return; }
            body.classList.remove('magazine-closed');
            body.classList.add('magazine-open');
            magazineCoverContainer.classList.add('open');
            setTimeout(() => { magazineGridContainer.style.display = 'block'; }, 300);
        });
        openMagazineBtn.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openMagazineBtn.click(); }
        });
        modalCloseButton.addEventListener('click', () => { fullContentModal.style.display = 'none'; });
        window.addEventListener('click', (e) => { if (e.target == fullContentModal) { fullContentModal.style.display = 'none'; } });
        sortLatestBtn.addEventListener('click', () => {
            allPublishedContent.sort((a, b) => new Date(b.published_at) - new Date(a.published_at));
            renderGrid(allPublishedContent);
            sortLatestBtn.classList.add('active');
            sortUpvotesBtn.classList.remove('active');
        });
        sortUpvotesBtn.addEventListener('click', () => {
            allPublishedContent.sort((a, b) => b.upvotes - a.upvotes);
            renderGrid(allPublishedContent);
            sortUpvotesBtn.classList.add('active');
            sortLatestBtn.classList.remove('active');
        });

        // Initial render
        renderGrid(allPublishedContent);
    });
    </script>
</body>
</html>