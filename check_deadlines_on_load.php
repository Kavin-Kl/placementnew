<?php
/**
 * Simple Deadline Checker (On-Demand)
 * This file is included whenever admin pages load to check for expired deadlines
 * No background tasks needed - notifications are created when you view the page
 */

// Only run if database connection exists and is valid
if (!isset($conn) || !($conn instanceof mysqli) || $conn->connect_error) {
    return;
}

// Check if required tables exist before running
$tables_check = @$conn->query("SHOW TABLES LIKE 'admin_notifications'");
if (!$tables_check || $tables_check->num_rows === 0) {
    return; // Tables don't exist yet, skip silently
}

try {
    // Find drives where deadline expired more than 1 hour ago and notification not sent
    $deadline_check_query = "
        SELECT
            d.drive_id,
            d.company_name,
            d.drive_no,
            d.close_date,
            COUNT(a.application_id) as total_applications
        FROM drives d
        LEFT JOIN applications a ON d.drive_id = a.drive_id
        WHERE d.close_date IS NOT NULL
        AND d.close_date <= NOW()
        AND d.close_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        AND TIMESTAMPDIFF(HOUR, d.close_date, NOW()) >= 1
        AND NOT EXISTS (
            SELECT 1 FROM deadline_notifications_sent dns
            WHERE dns.drive_id = d.drive_id
            AND dns.notification_type = 'deadline_expired'
        )
        GROUP BY d.drive_id
    ";

    $result = @$conn->query($deadline_check_query);

    if ($result && $result->num_rows > 0) {
        while ($drive = $result->fetch_assoc()) {
            $drive_id = $drive['drive_id'];
            $company_name = $drive['company_name'];
            $drive_no = $drive['drive_no'];
            $close_date = $drive['close_date'];
            $total_applications = $drive['total_applications'];

            // Calculate hours since deadline
            $close_timestamp = strtotime($close_date);
            $now = time();
            $hours_ago = round(($now - $close_timestamp) / 3600, 1);

            // Create notification
            $title = "Deadline Expired: $company_name";
            $message = "The application deadline for $company_name (Drive $drive_no) ended $hours_ago hour(s) ago.\n\n";
            $message .= "Total Applications Received: $total_applications\n\n";
            $message .= "Action Required:\n";
            $message .= "1. Review all applications\n";
            $message .= "2. Prepare applicant list for the company\n";
            $message .= "3. Share the list with the company HR\n\n";
            $message .= "Deadline was: " . date('d M Y, h:i A', $close_timestamp);

            $insert_notification = @$conn->prepare("
                INSERT INTO admin_notifications
                (drive_id, title, message, type, action_url)
                VALUES (?, ?, ?, 'deadline', ?)
            ");

            if ($insert_notification) {
                $action_url = "enrolled_students.php?drive_id=" . $drive_id;
                $insert_notification->bind_param(
                    "isss",
                    $drive_id,
                    $title,
                    $message,
                    $action_url
                );

                if ($insert_notification->execute()) {
                    // Mark notification as sent to avoid duplicates
                    $mark_sent = @$conn->prepare("
                        INSERT INTO deadline_notifications_sent (drive_id, notification_type)
                        VALUES (?, 'deadline_expired')
                    ");
                    if ($mark_sent) {
                        $mark_sent->bind_param("i", $drive_id);
                        @$mark_sent->execute();
                        @$mark_sent->close();
                    }
                }
                @$insert_notification->close();
            }
        }
    }

    // Also check for upcoming deadlines (24 hours)
    $reminder_query = "
        SELECT
            d.drive_id,
            d.company_name,
            d.drive_no,
            d.close_date,
            COUNT(a.application_id) as total_applications
        FROM drives d
        LEFT JOIN applications a ON d.drive_id = a.drive_id
        WHERE d.close_date IS NOT NULL
        AND d.close_date > NOW()
        AND d.close_date <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
        AND NOT EXISTS (
            SELECT 1 FROM deadline_notifications_sent dns
            WHERE dns.drive_id = d.drive_id
            AND dns.notification_type = 'deadline_reminder_24h'
        )
        GROUP BY d.drive_id
    ";

    $reminder_result = @$conn->query($reminder_query);

    if ($reminder_result && $reminder_result->num_rows > 0) {
        while ($drive = $reminder_result->fetch_assoc()) {
            $drive_id = $drive['drive_id'];
            $company_name = $drive['company_name'];
            $drive_no = $drive['drive_no'];
            $close_date = $drive['close_date'];
            $total_applications = $drive['total_applications'];

            $close_timestamp = strtotime($close_date);
            $hours_remaining = round(($close_timestamp - time()) / 3600, 1);

            $title = "Deadline Approaching: $company_name";
            $message = "The application deadline for $company_name (Drive $drive_no) is approaching.\n\n";
            $message .= "Deadline: " . date('d M Y, h:i A', $close_timestamp) . "\n";
            $message .= "Time Remaining: $hours_remaining hour(s)\n";
            $message .= "Current Applications: $total_applications\n\n";
            $message .= "This is a reminder to prepare for deadline closure.";

            $insert_notification = @$conn->prepare("
                INSERT INTO admin_notifications
                (drive_id, title, message, type, action_url)
                VALUES (?, ?, ?, 'reminder', ?)
            ");

            if ($insert_notification) {
                $action_url = "enrolled_students.php?drive_id=" . $drive_id;
                $insert_notification->bind_param(
                    "isss",
                    $drive_id,
                    $title,
                    $message,
                    $action_url
                );

                if ($insert_notification->execute()) {
                    $mark_sent = @$conn->prepare("
                        INSERT INTO deadline_notifications_sent (drive_id, notification_type)
                        VALUES (?, 'deadline_reminder_24h')
                    ");
                    if ($mark_sent) {
                        $mark_sent->bind_param("i", $drive_id);
                        @$mark_sent->execute();
                        @$mark_sent->close();
                    }
                }
                @$insert_notification->close();
            }
        }
    }

} catch (Exception $e) {
    // Silently fail - don't break the page if there's an error
}
?>
