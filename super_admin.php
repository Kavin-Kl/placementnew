<?php
session_start();
include("config.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: index.php");
    exit;
}

$super_admins = ['Asgar Ahmed'];
if (!isset($_SESSION['username']) || !in_array($_SESSION['username'], $super_admins, true)) {
    http_response_code(403);
    include 'header.php';
    echo '<div class="main"><div style="max-width:700px;margin:40px auto;padding:30px;background:#fff;border-radius:8px;box-shadow:0 1px 4px rgba(0,0,0,0.08);">
        <h2 style="color:#650000;">Access Denied</h2>
        <p>This page is restricted to super admins only.</p>
        <a href="dashboard.php" style="color:#650000;font-weight:600;">&larr; Back to Dashboard</a>
    </div></div>';
    exit;
}

if (empty($_SESSION['super_admin_csrf'])) {
    $_SESSION['super_admin_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['super_admin_csrf'];

$message = '';
$error = '';

function fetch_table_list(mysqli $conn): array {
    $tables = [];
    $res = $conn->query("SHOW TABLES");
    if ($res) {
        while ($row = $res->fetch_array(MYSQLI_NUM)) {
            $tables[] = $row[0];
        }
    }
    sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
    return $tables;
}

function fetch_columns(mysqli $conn, string $table): array {
    $cols = [];
    $stmt = $conn->prepare("SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_KEY, EXTRA, CHARACTER_MAXIMUM_LENGTH
                            FROM INFORMATION_SCHEMA.COLUMNS
                            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
                            ORDER BY ORDINAL_POSITION");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $cols[$row['COLUMN_NAME']] = $row;
    }
    $stmt->close();
    return $cols;
}

function primary_key_columns(array $columns): array {
    $pk = [];
    foreach ($columns as $name => $meta) {
        if ($meta['COLUMN_KEY'] === 'PRI') $pk[] = $name;
    }
    return $pk;
}

function mysql_type_bind_char(string $data_type): string {
    $t = strtolower($data_type);
    if (in_array($t, ['tinyint','smallint','mediumint','int','bigint'], true)) return 'i';
    if (in_array($t, ['float','double','decimal','numeric'], true)) return 'd';
    return 's';
}

$tables = fetch_table_list($conn);
$current_table = $_GET['table'] ?? '';
if ($current_table !== '' && !in_array($current_table, $tables, true)) {
    $error = "Unknown table: " . htmlspecialchars($current_table);
    $current_table = '';
}

$columns = $current_table ? fetch_columns($conn, $current_table) : [];
$pk_cols = $columns ? primary_key_columns($columns) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf']) || !hash_equals($csrf, $_POST['csrf'])) {
        $error = "Invalid security token. Please refresh and try again.";
    } else {
        $action = $_POST['action'] ?? '';
        $post_table = $_POST['table'] ?? '';
        if ($post_table === '' || !in_array($post_table, $tables, true)) {
            $error = "Invalid table.";
        } else {
            $post_columns = fetch_columns($conn, $post_table);
            $post_pk = primary_key_columns($post_columns);

            if (empty($post_pk)) {
                $error = "Table '" . htmlspecialchars($post_table) . "' has no primary key — edits and deletes are disabled for safety.";
            } elseif ($action === 'delete') {
                $pk_values = $_POST['pk'] ?? [];
                if ($post_table === 'admin_users'
                    && isset($pk_values['admin_id'])
                    && (int)$pk_values['admin_id'] === (int)$_SESSION['admin_id']) {
                    $error = "Refused: you cannot delete the admin row you are currently logged in as.";
                } else {
                    $where_parts = [];
                    $types = '';
                    $vals = [];
                    $ok = true;
                    foreach ($post_pk as $pk_name) {
                        if (!array_key_exists($pk_name, $pk_values)) { $ok = false; break; }
                        $where_parts[] = "`" . str_replace("`","``",$pk_name) . "` = ?";
                        $types .= mysql_type_bind_char($post_columns[$pk_name]['DATA_TYPE']);
                        $vals[] = $pk_values[$pk_name];
                    }
                    if (!$ok) {
                        $error = "Missing primary key value.";
                    } else {
                        $sql = "DELETE FROM `" . str_replace("`","``",$post_table) . "` WHERE " . implode(' AND ', $where_parts) . " LIMIT 1";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) {
                            $error = "Prepare failed: " . $conn->error;
                        } else {
                            $stmt->bind_param($types, ...$vals);
                            if ($stmt->execute()) {
                                $message = "Row deleted from `" . htmlspecialchars($post_table) . "`.";
                            } else {
                                $error = "Delete failed: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                    }
                }
            } elseif ($action === 'update') {
                $pk_values = $_POST['pk'] ?? [];
                $new_values = $_POST['fields'] ?? [];
                $set_parts = [];
                $types = '';
                $vals = [];
                foreach ($post_columns as $col_name => $meta) {
                    if (in_array($col_name, $post_pk, true)) continue;
                    if (stripos($meta['EXTRA'] ?? '', 'auto_increment') !== false) continue;
                    if (!array_key_exists($col_name, $new_values)) continue;
                    $raw = $new_values[$col_name];
                    $is_null_box = isset($_POST['null'][$col_name]);
                    $set_parts[] = "`" . str_replace("`","``",$col_name) . "` = ?";
                    if ($is_null_box && $meta['IS_NULLABLE'] === 'YES') {
                        $types .= 's';
                        $vals[] = null;
                    } else {
                        $types .= mysql_type_bind_char($meta['DATA_TYPE']);
                        $vals[] = $raw;
                    }
                }
                if (empty($set_parts)) {
                    $error = "Nothing to update.";
                } else {
                    $where_parts = [];
                    $ok = true;
                    foreach ($post_pk as $pk_name) {
                        if (!array_key_exists($pk_name, $pk_values)) { $ok = false; break; }
                        $where_parts[] = "`" . str_replace("`","``",$pk_name) . "` = ?";
                        $types .= mysql_type_bind_char($post_columns[$pk_name]['DATA_TYPE']);
                        $vals[] = $pk_values[$pk_name];
                    }
                    if (!$ok) {
                        $error = "Missing primary key value.";
                    } else {
                        $sql = "UPDATE `" . str_replace("`","``",$post_table) . "` SET " . implode(', ', $set_parts)
                             . " WHERE " . implode(' AND ', $where_parts) . " LIMIT 1";
                        $stmt = $conn->prepare($sql);
                        if (!$stmt) {
                            $error = "Prepare failed: " . $conn->error;
                        } else {
                            $stmt->bind_param($types, ...$vals);
                            if ($stmt->execute()) {
                                $message = "Row updated in `" . htmlspecialchars($post_table) . "`.";
                            } else {
                                $error = "Update failed: " . $stmt->error;
                            }
                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}

$edit_row = null;
if ($current_table && isset($_GET['edit']) && $_GET['edit'] === '1' && !empty($pk_cols)) {
    $where_parts = [];
    $types = '';
    $vals = [];
    $ok = true;
    foreach ($pk_cols as $pk_name) {
        if (!isset($_GET['pk'][$pk_name])) { $ok = false; break; }
        $where_parts[] = "`" . str_replace("`","``",$pk_name) . "` = ?";
        $types .= mysql_type_bind_char($columns[$pk_name]['DATA_TYPE']);
        $vals[] = $_GET['pk'][$pk_name];
    }
    if ($ok) {
        $sql = "SELECT * FROM `" . str_replace("`","``",$current_table) . "` WHERE " . implode(' AND ', $where_parts) . " LIMIT 1";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$vals);
            $stmt->execute();
            $r = $stmt->get_result();
            $edit_row = $r ? $r->fetch_assoc() : null;
            $stmt->close();
        }
    }
}

$rows = [];
$total_rows = 0;
$pg = null;
if ($current_table && !$edit_row) {
    require_once __DIR__ . '/pagination_helper.php';
    $count_res = $conn->query("SELECT COUNT(*) AS c FROM `" . str_replace("`","``",$current_table) . "`");
    $total_rows = $count_res ? (int)$count_res->fetch_assoc()['c'] : 0;
    $pg = paginate_setup($total_rows, 25, 'page');
    $list_res = $conn->query("SELECT * FROM `" . str_replace("`","``",$current_table) . "` LIMIT {$pg['per_page']} OFFSET {$pg['offset']}");
    $rows = $list_res ? $list_res->fetch_all(MYSQLI_ASSOC) : [];
}

include 'header.php';
?>
<style>
.sa-wrap { max-width: 1200px; margin: 0 auto; }
.sa-wrap h2 { color: #650000; margin-bottom: 10px; }
.sa-banner {
    background: #fff8e1; border-left: 4px solid #f5a623; padding: 10px 14px;
    border-radius: 4px; margin-bottom: 20px; font-size: 13px; color: #6b4a00;
}
.sa-msg { background:#d4edda; color:#155724; padding:10px; border-radius:4px; margin-bottom:15px; }
.sa-err { background:#f8d7da; color:#721c24; padding:10px; border-radius:4px; margin-bottom:15px; }
.sa-tables-grid {
    display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 10px; margin-top: 15px;
}
.sa-table-card {
    background: #fff; padding: 12px 14px; border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06); text-decoration: none; color: #650000;
    font-weight: 600; font-size: 13px; transition: all .15s ease;
    border: 1px solid #eee;
}
.sa-table-card:hover { border-color: #650000; transform: translateY(-1px); }
.sa-toolbar { display:flex; align-items:center; gap:10px; margin: 10px 0 20px; flex-wrap: wrap; }
.sa-toolbar a { color:#650000; text-decoration:none; font-weight:600; font-size:13px; }
.sa-toolbar a:hover { text-decoration: underline; }
.sa-table-wrap { overflow-x: auto; background: #fff; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.06); }
.sa-data-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.sa-data-table th {
    background: #650000; color: #fff; text-align: left; padding: 8px 10px;
    font-weight: 600; white-space: nowrap;
}
.sa-data-table th.pk { background: #4a0000; }
.sa-data-table td {
    padding: 6px 10px; border-bottom: 1px solid #eee;
    max-width: 320px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.sa-data-table td.actions { white-space: nowrap; }
.sa-btn {
    display: inline-block; padding: 4px 10px; font-size: 12px; font-weight: 600;
    border: none; border-radius: 3px; cursor: pointer; color: #fff; text-decoration: none;
}
.sa-btn-edit { background: #17a2b8; }
.sa-btn-edit:hover { background: #117a8b; }
.sa-btn-del { background: #dc3545; }
.sa-btn-del:hover { background: #a71d2a; }
.sa-btn-save { background: #28a745; }
.sa-btn-save:hover { background: #1e7e34; }
.sa-btn-cancel { background: #6c757d; }
.sa-btn-cancel:hover { background: #545b62; }
.sa-action-form { display: inline-block; margin: 0 2px; }
.sa-edit-form {
    background: #fff; padding: 20px; border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
}
.sa-edit-form .field { margin-bottom: 14px; }
.sa-edit-form label {
    display: block; font-weight: 600; font-size: 12px;
    color: #444; margin-bottom: 4px;
}
.sa-edit-form .meta { color: #888; font-weight: 400; font-size: 11px; margin-left: 6px; }
.sa-edit-form input[type=text], .sa-edit-form textarea {
    width: 100%; padding: 7px 9px; border: 1px solid #ccc; border-radius: 4px;
    font-size: 13px; font-family: inherit; box-sizing: border-box;
}
.sa-edit-form textarea { min-height: 70px; resize: vertical; }
.sa-edit-form .null-row { font-size: 11px; color: #666; margin-top: 4px; }
.sa-edit-form .pk-display {
    background: #f4f6f8; padding: 8px 10px; border-radius: 4px;
    font-family: monospace; font-size: 12px;
}
.sa-form-actions { margin-top: 18px; display: flex; gap: 10px; }
.sa-null-cell { color: #aaa; font-style: italic; }
</style>

<div class="sa-wrap">
    <h2><i class="bi bi-shield-lock"></i> Database Tables (Super Admin)</h2>
    <div class="sa-banner">
        <strong>Heads up:</strong> Changes here write directly to the live database.
        Edits and deletes are permanent and cannot be undone from this screen.
    </div>

    <?php if ($message): ?><div class="sa-msg"><?= htmlspecialchars($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="sa-err"><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <?php if (!$current_table): ?>
        <h3 style="color:#444;font-size:16px;">All Tables (<?= count($tables) ?>)</h3>
        <div class="sa-tables-grid">
            <?php foreach ($tables as $t): ?>
                <a class="sa-table-card" href="super_admin.php?table=<?= urlencode($t) ?>">
                    <i class="bi bi-table"></i> <?= htmlspecialchars($t) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php elseif ($edit_row): ?>
        <div class="sa-toolbar">
            <a href="super_admin.php">&larr; All Tables</a> &nbsp;/&nbsp;
            <a href="super_admin.php?table=<?= urlencode($current_table) ?>"><?= htmlspecialchars($current_table) ?></a>
            &nbsp;/&nbsp; <span>Edit Row</span>
        </div>
        <form method="POST" class="sa-edit-form">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="table" value="<?= htmlspecialchars($current_table) ?>">

            <?php foreach ($pk_cols as $pk_name): ?>
                <input type="hidden" name="pk[<?= htmlspecialchars($pk_name) ?>]" value="<?= htmlspecialchars((string)$edit_row[$pk_name]) ?>">
            <?php endforeach; ?>

            <div class="field">
                <label>Primary Key</label>
                <div class="pk-display">
                    <?php foreach ($pk_cols as $pk_name): ?>
                        <?= htmlspecialchars($pk_name) ?> = <?= htmlspecialchars((string)$edit_row[$pk_name]) ?><br>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php foreach ($columns as $col_name => $meta):
                if (in_array($col_name, $pk_cols, true)) continue;
                if (stripos($meta['EXTRA'] ?? '', 'auto_increment') !== false) continue;
                $val = $edit_row[$col_name];
                $is_long = in_array(strtolower($meta['DATA_TYPE']), ['text','mediumtext','longtext','blob','json'], true)
                           || (!empty($meta['CHARACTER_MAXIMUM_LENGTH']) && (int)$meta['CHARACTER_MAXIMUM_LENGTH'] > 255);
            ?>
                <div class="field">
                    <label>
                        <?= htmlspecialchars($col_name) ?>
                        <span class="meta">
                            <?= htmlspecialchars($meta['DATA_TYPE']) ?><?php
                                if ($meta['CHARACTER_MAXIMUM_LENGTH']) echo '(' . (int)$meta['CHARACTER_MAXIMUM_LENGTH'] . ')';
                                if ($meta['IS_NULLABLE'] === 'YES') echo ' • nullable';
                            ?>
                        </span>
                    </label>
                    <?php if ($is_long): ?>
                        <textarea name="fields[<?= htmlspecialchars($col_name) ?>]"><?= $val === null ? '' : htmlspecialchars((string)$val) ?></textarea>
                    <?php else: ?>
                        <input type="text" name="fields[<?= htmlspecialchars($col_name) ?>]" value="<?= $val === null ? '' : htmlspecialchars((string)$val) ?>">
                    <?php endif; ?>
                    <?php if ($meta['IS_NULLABLE'] === 'YES'): ?>
                        <div class="null-row">
                            <label style="display:inline; font-weight: 400;">
                                <input type="checkbox" name="null[<?= htmlspecialchars($col_name) ?>]" value="1" <?= $val === null ? 'checked' : '' ?>>
                                Set to NULL
                            </label>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div class="sa-form-actions">
                <button type="submit" class="sa-btn sa-btn-save">Save Changes</button>
                <a class="sa-btn sa-btn-cancel" href="super_admin.php?table=<?= urlencode($current_table) ?>">Cancel</a>
            </div>
        </form>
    <?php else: ?>
        <div class="sa-toolbar">
            <a href="super_admin.php">&larr; All Tables</a> &nbsp;/&nbsp;
            <strong><?= htmlspecialchars($current_table) ?></strong>
            <span style="color:#666;">(<?= number_format($total_rows) ?> rows)</span>
            <?php if (empty($pk_cols)): ?>
                <span style="color:#a00; margin-left:auto; font-size:12px;">
                    No primary key — edit/delete disabled
                </span>
            <?php endif; ?>
        </div>

        <?php if (empty($rows)): ?>
            <div style="background:#fff;padding:20px;border-radius:6px;text-align:center;color:#666;">
                No rows in this table.
            </div>
        <?php else: ?>
            <div class="sa-table-wrap">
                <table class="sa-data-table">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($columns) as $col_name): ?>
                                <th class="<?= in_array($col_name, $pk_cols, true) ? 'pk' : '' ?>">
                                    <?= htmlspecialchars($col_name) ?>
                                </th>
                            <?php endforeach; ?>
                            <?php if (!empty($pk_cols)): ?><th>Actions</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $row): ?>
                            <tr>
                                <?php foreach (array_keys($columns) as $col_name):
                                    $v = $row[$col_name] ?? null;
                                ?>
                                    <td title="<?= htmlspecialchars((string)$v) ?>">
                                        <?php if ($v === null): ?>
                                            <span class="sa-null-cell">NULL</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars(mb_strimwidth((string)$v, 0, 120, '…')) ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <?php if (!empty($pk_cols)): ?>
                                    <td class="actions">
                                        <?php
                                            $edit_url = 'super_admin.php?table=' . urlencode($current_table) . '&edit=1';
                                            foreach ($pk_cols as $pk_name) {
                                                $edit_url .= '&pk[' . urlencode($pk_name) . ']=' . urlencode((string)$row[$pk_name]);
                                            }
                                        ?>
                                        <a class="sa-btn sa-btn-edit" href="<?= htmlspecialchars($edit_url) ?>">Edit</a>
                                        <form method="POST" class="sa-action-form"
                                              onsubmit="return confirm('Permanently delete this row from <?= htmlspecialchars($current_table) ?>?');">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="table" value="<?= htmlspecialchars($current_table) ?>">
                                            <?php foreach ($pk_cols as $pk_name): ?>
                                                <input type="hidden" name="pk[<?= htmlspecialchars($pk_name) ?>]" value="<?= htmlspecialchars((string)$row[$pk_name]) ?>">
                                            <?php endforeach; ?>
                                            <button type="submit" class="sa-btn sa-btn-del">Delete</button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?= $pg ? render_pagination($pg) : '' ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
