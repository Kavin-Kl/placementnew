<?php
session_start();
if (!isset($_SESSION['student_id'])) {
    header("Location: student_login.php");
    exit;
}

include("config.php");

$student_id = $_SESSION['student_id'];

// Mark notification as read if requested
if (isset($_GET['mark_read']) && is_numeric($_GET['mark_read'])) {
    $notif_id = intval($_GET['mark_read']);
    $mark_stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1 WHERE notification_id = ? AND student_id = ?");
    $mark_stmt->bind_param("ii", $notif_id, $student_id);
    $mark_stmt->execute();
    header("Location: student_notifications.php");
    exit;
}

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $mark_all_stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1 WHERE student_id = ?");
    $mark_all_stmt->bind_param("i", $student_id);
    $mark_all_stmt->execute();
    header("Location: student_notifications.php");
    exit;
}

// Delete notification
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $notif_id = intval($_GET['delete']);
    $delete_stmt = $conn->prepare("DELETE FROM student_notifications WHERE notification_id = ? AND student_id = ?");
    $delete_stmt->bind_param("ii", $notif_id, $student_id);
    $delete_stmt->execute();
    header("Location: student_notifications.php");
    exit;
}

// Now include header after all redirects are handled
include("student_header.php");

// Fetch all notifications
$notif_query = "
    SELECT * FROM student_notifications
    WHERE student_id = ?
    ORDER BY created_at DESC
";
$notif_stmt = $conn->prepare($notif_query);
$notif_stmt->bind_param("i", $student_id);
$notif_stmt->execute();
$notifications = $notif_stmt->get_result();

// Get unread count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread FROM student_notifications WHERE student_id = ? AND is_read = 0");
$unread_stmt->bind_param("i", $student_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->get_result()->fetch_assoc()['unread'];
?>

<div class="home-section">
  <div class="container-fluid">
    <div class="row mb-4">
      <div class="col-md-8">
        <h2>Notifications</h2>
        <p class="text-muted">Stay updated with your placement activities</p>
      </div>
      <div class="col-md-4 text-end">
        <?php if ($unread_count > 0): ?>
          <a href="?mark_all_read=1" class="btn btn-sm btn-outline-primary">
            <i class="bx bx-check-double"></i> Mark All as Read
          </a>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($unread_count > 0): ?>
      <div class="alert alert-info">
        <i class="bx bx-info-circle"></i> You have <strong><?= $unread_count ?></strong> unread notification(s)
      </div>
    <?php endif; ?>

    <?php if ($notifications->num_rows > 0): ?>
      <div class="row">
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
              <div class="list-group list-group-flush">
                <?php while ($notif = $notifications->fetch_assoc()):
                  $icon = match($notif['type']) {
                    'drive' => 'bx-briefcase',
                    'application' => 'bx-file',
                    'placement' => 'bx-check-circle',
                    default => 'bx-bell'
                  };

                  $icon_color = match($notif['type']) {
                    'drive' => 'text-primary',
                    'application' => 'text-info',
                    'placement' => 'text-success',
                    default => 'text-secondary'
                  };
                ?>
                  <div class="list-group-item list-group-item-action <?= !$notif['is_read'] ? 'bg-light' : '' ?>">
                    <div class="d-flex w-100 align-items-start">
                      <div class="me-3">
                        <i class="bx <?= $icon ?> <?= $icon_color ?>" style="font-size: 32px;"></i>
                      </div>
                      <div class="flex-grow-1">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                          <h6 class="mb-1">
                            <?= htmlspecialchars($notif['title']) ?>
                            <?php if (!$notif['is_read']): ?>
                              <span class="badge bg-danger badge-sm">New</span>
                            <?php endif; ?>
                          </h6>
                          <small class="text-muted">
                            <?php
                            $time_diff = time() - strtotime($notif['created_at']);
                            if ($time_diff < 60) {
                              echo 'Just now';
                            } elseif ($time_diff < 3600) {
                              echo floor($time_diff / 60) . ' min ago';
                            } elseif ($time_diff < 86400) {
                              echo floor($time_diff / 3600) . ' hours ago';
                            } else {
                              echo date('M d, Y', strtotime($notif['created_at']));
                            }
                            ?>
                          </small>
                        </div>
                        <p class="mb-2"><?= htmlspecialchars($notif['message']) ?></p>
                        <div class="btn-group btn-group-sm" role="group">
                          <?php if (!$notif['is_read']): ?>
                            <a href="?mark_read=<?= $notif['notification_id'] ?>" class="btn btn-outline-primary btn-sm">
                              <i class="bx bx-check"></i> Mark as Read
                            </a>
                          <?php endif; ?>
                          <a href="?delete=<?= $notif['notification_id'] ?>" class="btn btn-outline-danger btn-sm"
                             onclick="return confirm('Delete this notification?')">
                            <i class="bx bx-trash"></i> Delete
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endwhile; ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php else: ?>
      <div class="row">
        <div class="col-12">
          <div class="card border-0 shadow-sm">
            <div class="card-body text-center py-5">
              <i class="bx bx-bell-off" style="font-size: 64px; color: #ccc;"></i>
              <h4 class="mt-3">No Notifications</h4>
              <p class="text-muted">You're all caught up! No new notifications at the moment.</p>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<style>
.list-group-item {
  border-left: 4px solid transparent;
  transition: all 0.2s ease;
}

.list-group-item:hover {
  border-left-color: #581729;
}

.list-group-item.bg-light {
  border-left-color: #007bff;
  background-color: #f8f9ff !important;
}

.badge-sm {
  font-size: 10px;
  padding: 2px 6px;
}
</style>

</body>
</html>
