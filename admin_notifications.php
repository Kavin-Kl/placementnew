<?php
session_start();
require 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['username'])) {
    header("Location: index.php");
    exit();
}

$admin_username = $_SESSION['username'] ?? null;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_read') {
        $notification_id = intval($_POST['notification_id']);
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE notification_id = ?");
        $stmt->bind_param("i", $notification_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit();
    }

    if ($action === 'mark_all_read') {
        $stmt = $conn->prepare("UPDATE admin_notifications SET is_read = 1 WHERE is_read = 0");

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit();
    }

    if ($action === 'delete') {
        $notification_id = intval($_POST['notification_id']);
        $stmt = $conn->prepare("DELETE FROM admin_notifications WHERE notification_id = ?");
        $stmt->bind_param("i", $notification_id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => $conn->error]);
        }
        exit();
    }

    if ($action === 'get_count') {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0");
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        echo json_encode(['count' => $result['count']]);
        exit();
    }
}

// Fetch all notifications
$notifications_query = "
    SELECT
        an.*,
        d.company_name,
        d.drive_no
    FROM admin_notifications an
    LEFT JOIN drives d ON an.drive_id = d.drive_id
    ORDER BY an.created_at DESC
    LIMIT 100
";
$notifications_result = $conn->query($notifications_query);

// Get unread count
$unread_count_query = "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0";
$unread_count = $conn->query($unread_count_query)->fetch_assoc()['count'];

require 'header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class='bx bx-bell'></i> Notifications
                        <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </h5>
                    <?php if ($unread_count > 0): ?>
                        <button class="btn btn-sm btn-primary" id="markAllReadBtn">
                            <i class='bx bx-check-double'></i> Mark All as Read
                        </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if ($notifications_result->num_rows > 0): ?>
                        <div class="list-group list-group-flush">
                            <?php while ($notification = $notifications_result->fetch_assoc()): ?>
                                <?php
                                $type_icons = [
                                    'deadline' => 'bx-time-five',
                                    'application' => 'bx-file',
                                    'system' => 'bx-info-circle',
                                    'reminder' => 'bx-bell'
                                ];
                                $type_colors = [
                                    'deadline' => 'danger',
                                    'application' => 'primary',
                                    'system' => 'info',
                                    'reminder' => 'warning'
                                ];
                                $icon = $type_icons[$notification['type']] ?? 'bx-bell';
                                $color = $type_colors[$notification['type']] ?? 'secondary';

                                // Calculate time ago
                                $time_ago = '';
                                $created = strtotime($notification['created_at']);
                                $now = time();
                                $diff = $now - $created;

                                if ($diff < 60) {
                                    $time_ago = 'Just now';
                                } elseif ($diff < 3600) {
                                    $minutes = floor($diff / 60);
                                    $time_ago = $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
                                } elseif ($diff < 86400) {
                                    $hours = floor($diff / 3600);
                                    $time_ago = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
                                } else {
                                    $days = floor($diff / 86400);
                                    $time_ago = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
                                }
                                ?>
                                <div class="list-group-item notification-item <?php echo $notification['is_read'] ? '' : 'notification-unread'; ?>"
                                     data-notification-id="<?php echo $notification['notification_id']; ?>">
                                    <div class="d-flex w-100">
                                        <div class="notification-icon me-3">
                                            <i class='bx <?php echo $icon; ?> text-<?php echo $color; ?>' style="font-size: 24px;"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <h6 class="mb-1 <?php echo $notification['is_read'] ? 'text-muted' : ''; ?>">
                                                    <?php echo htmlspecialchars($notification['title']); ?>
                                                    <?php if (!$notification['is_read']): ?>
                                                        <span class="badge bg-primary ms-2">New</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <small class="text-muted"><?php echo $time_ago; ?></small>
                                            </div>
                                            <p class="mb-1 <?php echo $notification['is_read'] ? 'text-muted' : ''; ?>">
                                                <?php echo nl2br(htmlspecialchars($notification['message'])); ?>
                                            </p>
                                            <?php if ($notification['company_name']): ?>
                                                <small class="text-muted">
                                                    <i class='bx bx-building'></i> <?php echo htmlspecialchars($notification['company_name']); ?>
                                                    (Drive <?php echo $notification['drive_no']; ?>)
                                                </small>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <?php if ($notification['action_url']): ?>
                                                    <a href="<?php echo htmlspecialchars($notification['action_url']); ?>"
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class='bx bx-link-external'></i> View Details
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!$notification['is_read']): ?>
                                                    <button class="btn btn-sm btn-outline-success mark-read-btn"
                                                            data-id="<?php echo $notification['notification_id']; ?>">
                                                        <i class='bx bx-check'></i> Mark as Read
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-outline-danger delete-btn"
                                                        data-id="<?php echo $notification['notification_id']; ?>">
                                                    <i class='bx bx-trash'></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class='bx bx-bell-off' style="font-size: 64px; color: #ccc;"></i>
                            <p class="text-muted mt-3">No notifications yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.notification-item {
    border-left: 3px solid transparent;
    transition: all 0.2s;
}

.notification-unread {
    background-color: #f8f9fa;
    border-left-color: #0d6efd;
}

.notification-item:hover {
    background-color: #e9ecef;
}

.notification-icon {
    flex-shrink: 0;
}
</style>

<script>
$(document).ready(function() {
    // Mark single notification as read
    $('.mark-read-btn').on('click', function(e) {
        e.preventDefault();
        const notificationId = $(this).data('id');
        const notificationItem = $(this).closest('.notification-item');

        $.ajax({
            url: 'admin_notifications',
            method: 'POST',
            data: {
                action: 'mark_read',
                notification_id: notificationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    notificationItem.removeClass('notification-unread');
                    notificationItem.find('h6').addClass('text-muted');
                    notificationItem.find('p').addClass('text-muted');
                    notificationItem.find('.badge.bg-primary').remove();
                    notificationItem.find('.mark-read-btn').remove();
                    updateNotificationCount();
                    location.reload();
                } else {
                    alert('Failed to mark notification as read: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                alert('Failed to mark notification as read. Please try again.');
            }
        });
    });

    // Mark all as read
    $('#markAllReadBtn').on('click', function(e) {
        e.preventDefault();

        $.ajax({
            url: 'admin_notifications',
            method: 'POST',
            data: {
                action: 'mark_all_read'
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Failed to mark all notifications as read: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                alert('Failed to mark all notifications as read. Please try again.');
            }
        });
    });

    // Delete notification
    $('.delete-btn').on('click', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to delete this notification?')) {
            return;
        }

        const notificationId = $(this).data('id');
        const notificationItem = $(this).closest('.notification-item');

        $.ajax({
            url: 'admin_notifications',
            method: 'POST',
            data: {
                action: 'delete',
                notification_id: notificationId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    notificationItem.fadeOut(300, function() {
                        $(this).remove();
                        updateNotificationCount();
                        location.reload();
                    });
                } else {
                    alert('Failed to delete notification: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.error('Response:', xhr.responseText);
                alert('Failed to delete notification. Please try again.');
            }
        });
    });

    function updateNotificationCount() {
        $.ajax({
            url: 'admin_notifications',
            method: 'POST',
            data: {
                action: 'get_count'
            },
            dataType: 'json',
            success: function(response) {
                const count = response.count;
                if (count > 0) {
                    $('.notification-badge').text(count).show();
                } else {
                    $('.notification-badge').hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to update notification count:', error);
            }
        });
    }
});
</script>

<?php require 'footer.php'; ?>
