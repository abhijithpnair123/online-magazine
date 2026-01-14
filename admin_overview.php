<?php
session_start();
include 'db_connect.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['usertype'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// Admin specific data fetching (example)
$total_students = $conn->query("SELECT COUNT(*) FROM tbl_student")->fetch_row()[0];
$total_staff = $conn->query("SELECT COUNT(*) FROM tbl_staff WHERE role = 'staff'")->fetch_row()[0];
$pending_approvals = $conn->query("SELECT COUNT(*) FROM tbl_content_approval WHERE status = 'pending'")->fetch_row()[0];

// --- NEW: Magazine Report Data Fetching ---
$total_published_content = $conn->query("SELECT COUNT(*) FROM tbl_content WHERE published_at IS NOT NULL")->fetch_row()[0];
$total_upvotes_all_content = $conn->query("SELECT COALESCE(SUM(upvotes), 0) FROM tbl_content")->fetch_row()[0];
$total_comments_all_content = $conn->query("SELECT COALESCE(SUM(comments_count), 0) FROM tbl_content WHERE comments_count IS NOT NULL")->fetch_row()[0];

// Fetch top 5 most upvoted content
$top_upvoted_content = [];
$stmt_top_upvoted = $conn->prepare("SELECT title, upvotes FROM tbl_content WHERE published_at IS NOT NULL ORDER BY upvotes DESC LIMIT 5");
$stmt_top_upvoted->execute();
$result_top_upvoted = $stmt_top_upvoted->get_result();
while ($row = $result_top_upvoted->fetch_assoc()) {
    $top_upvoted_content[] = $row;
}
$stmt_top_upvoted->close();

// Fetch top 5 most commented content
$top_commented_content = [];
$stmt_top_commented = $conn->prepare("SELECT title, comments_count FROM tbl_content WHERE published_at IS NOT NULL AND comments_count IS NOT NULL AND comments_count > 0 ORDER BY comments_count DESC LIMIT 5");
$stmt_top_commented->execute();
$result_top_commented = $stmt_top_commented->get_result();
while ($row = $result_top_commented->fetch_assoc()) {
    $top_commented_content[] = $row;
}
$stmt_top_commented->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Subtle reveal animation for dashboard elements */
        .reveal { opacity: 0; transform: translateY(24px); transition: opacity 600ms ease, transform 600ms ease; }
        .reveal.in-view { opacity: 1; transform: translateY(0); }

        /* Ripple effect utility (matches theme) */
        .ripple-container { position: relative; overflow: hidden; }
        .ripple-wave {
            position: absolute; border-radius: 50%; transform: scale(0);
            background: rgba(0, 191, 165, 0.35); pointer-events: none;
            animation: rippleAnim 700ms ease-out forwards;
        }
        @keyframes rippleAnim { to { transform: scale(4); opacity: 0; } }
    </style>
 </head>
<body>
    <?php include 'includes/header.php'; ?>
    <div class="dashboard-container">
        <?php include 'includes/sidebar.php'; ?>
      
        <div class="main-content">
            <h1>Admin Overview</h1>
            <div class="reveal" style="margin-top: 20px; background-color: var(--color-bg, #F8F9FA); padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid rgba(0, 191, 165, 0.15);">
            <div class="dashboard-stats">
                <div class="stat-box reveal">
                    <h3>Total Students</h3>
                    <p><?php echo $total_students; ?></p>
                </div>
                <div class="stat-box reveal">
                    <h3>Total Staff</h3>
                    <p><?php echo $total_staff; ?></p>
                </div>
                <div class="stat-box reveal">
                    <h3>Pending Content Approvals</h3>
                    <p><?php echo $pending_approvals; ?></p>
                </div>
            </div>
            </div>

            <!-- NEW: Magazine Reports Section -->
            <div class="report-section reveal" style="margin-top: 40px; background-color: var(--color-bg, #F8F9FA); padding: 30px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid rgba(0, 191, 165, 0.15);">
                <h2><i class="fas fa-chart-bar" style="margin-right: 10px; color: var(--color-primary, #00BFA5);"></i>Magazine Activity Reports</h2>
                <p style="margin-bottom: 25px; color: var(--color-muted, #4B5C66);">Overview of content engagement and popularity.</p>

                <div class="dashboard-stats" style="display: flex; flex-wrap: wrap; gap: 20px; justify-content: space-between;">
                    <div class="stat-box reveal" style="flex: 1 1 calc(33% - 20px); min-width: 280px; background: rgba(0, 191, 165, 0.06); border: 1px solid rgba(0, 191, 165, 0.25); color: var(--color-text, #2B3A42);">
                        <h3>Total Published Content</h3>
                        <p style="font-size: 2em; font-weight: bold;"><?php echo $total_published_content; ?></p>
                    </div>
                    <div class="stat-box reveal" style="flex: 1 1 calc(33% - 20px); min-width: 280px; background: rgba(255, 140, 66, 0.06); border: 1px solid rgba(255, 140, 66, 0.25); color: var(--color-text, #2B3A42);">
                        <h3>Total Upvotes Received</h3>
                        <p style="font-size: 2em; font-weight: bold;"><?php echo $total_upvotes_all_content; ?></p>
                    </div>
                    <div class="stat-box reveal" style="flex: 1 1 calc(33% - 20px); min-width: 280px; background: rgba(0, 191, 165, 0.06); border: 1px solid rgba(0, 191, 165, 0.25); color: var(--color-text, #2B3A42);">
                        <h3>Total Comments Posted</h3>
                        <p style="font-size: 2em; font-weight: bold;"><?php echo $total_comments_all_content; ?></p>
                    </div>
                </div>

                <div style="display: flex; flex-wrap: wrap; gap: 30px; margin-top: 30px;">
                    <div class="reveal" style="flex: 1 1 calc(50% - 15px); min-width: 300px; background: var(--color-surface, #FFFFFF); padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid rgba(0, 191, 165, 0.12);">
                        <h3 style="color: var(--color-text, #2B3A42); margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;"><i class="fas fa-fire" style="margin-right: 8px; color: var(--color-primary, #00BFA5);"></i>Most Upvoted Content</h3>
                        <?php if (count($top_upvoted_content) > 0): ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ($top_upvoted_content as $content): ?>
                                    <li style="padding: 10px 0; border-bottom: 1px dashed #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                                        <span style="color: var(--color-text, #2B3A42); font-weight: 500;"><?php echo htmlspecialchars($content['title']); ?></span>
                                        <span style="background: var(--color-primary, #00BFA5); color: #F8F9FA; padding: 4px 8px; border-radius: 12px; font-size: 0.85em;"><?php echo $content['upvotes']; ?> Upvotes</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="color: var(--color-muted, #4B5C66); text-align: center;">No upvoted content yet.</p>
                        <?php endif; ?>
                    </div>

                    <div class="reveal" style="flex: 1 1 calc(50% - 15px); min-width: 300px; background: var(--color-surface, #FFFFFF); padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); border: 1px solid rgba(0, 191, 165, 0.12);">
                        <h3 style="color: var(--color-text, #2B3A42); margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;"><i class="fas fa-comments" style="margin-right: 8px; color: var(--color-primary-2, #33CDB9);"></i>Most Commented Content</h3>
                        <?php if (count($top_commented_content) > 0): ?>
                            <ul style="list-style: none; padding: 0;">
                                <?php foreach ($top_commented_content as $content): ?>
                                    <li style="padding: 10px 0; border-bottom: 1px dashed #f0f0f0; display: flex; justify-content: space-between; align-items: center;">
                                        <span style="color: var(--color-text, #2B3A42); font-weight: 500;"><?php echo htmlspecialchars($content['title']); ?></span>
                                        <span style="background: var(--color-primary, #00BFA5); color: #F8F9FA; padding: 4px 8px; border-radius: 12px; font-size: 0.85em;"><?php echo $content['comments_count']; ?> Comments</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p style="color: var(--color-muted, #4B5C66); text-align: center;">No commented content yet.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <p style="margin-top: 30px; text-align: center; font-size: 0.9em; color: var(--color-muted, #4B5C66);">
                    Data last updated: <?php echo date('Y-m-d H:i:s'); ?>
                </p>
            </div>
            <!-- END NEW: Magazine Reports Section -->

        </div>
    </div>
</body>
<script>
(function() {
  // IntersectionObserver for reveal-on-scroll
  const io = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('in-view');
        io.unobserve(entry.target);
      }
    });
  }, { threshold: 0.15 });
  document.querySelectorAll('.reveal').forEach(el => io.observe(el));

  // Animated counters for all stat-box numbers
  function animateCount(el, endValue, duration) {
    const start = 0;
    const startTime = performance.now();
    function tick(now) {
      const elapsed = now - startTime;
      const progress = Math.min(elapsed / duration, 1);
      const eased = 1 - Math.pow(1 - progress, 3); // easeOutCubic
      el.textContent = Math.floor(start + (endValue - start) * eased);
      if (progress < 1) requestAnimationFrame(tick);
      else el.textContent = endValue.toLocaleString();
    }
    requestAnimationFrame(tick);
  }
  document.querySelectorAll('.stat-box p').forEach(p => {
    const value = parseInt((p.textContent || '0').replace(/[^0-9]/g, ''), 10) || 0;
    p.dataset.target = value;
    p.textContent = '0';
    animateCount(p, value, 1200);
  });

  // Ripple effect on primary/action buttons
  function attachRipple(selector) {
    document.querySelectorAll(selector).forEach(btn => {
      btn.classList.add('ripple-container');
      btn.addEventListener('click', function(e) {
        const rect = this.getBoundingClientRect();
        const size = Math.max(rect.width, rect.height) * 1.2;
        const wave = document.createElement('span');
        wave.className = 'ripple-wave';
        wave.style.width = wave.style.height = size + 'px';
        wave.style.left = (e.clientX - rect.left - size/2) + 'px';
        wave.style.top = (e.clientY - rect.top - size/2) + 'px';
        this.appendChild(wave);
        setTimeout(() => wave.remove(), 700);
      });
    });
  }
  attachRipple('.btn, .btn-primary, .btn-action');
})();
</script>
</html>
