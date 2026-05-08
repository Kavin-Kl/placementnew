<?php
// Helpers for the read-only "Admin Preview" student account.
// The account uses reg_no = 'ADMIN_PREVIEW' and exists so admins can log in to
// the student portal and see every drive — filters by academic year,
// graduating year, and placement category are bypassed for this account.

if (!defined('ADMIN_PREVIEW_REG_NO')) {
    define('ADMIN_PREVIEW_REG_NO', 'ADMIN_PREVIEW');
}
if (!defined('ADMIN_PREVIEW_UPID')) {
    define('ADMIN_PREVIEW_UPID', 'ADMIN_PREVIEW');
}

function is_admin_preview(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    $upid = $_SESSION['student_upid'] ?? '';
    return $upid === ADMIN_PREVIEW_UPID;
}
