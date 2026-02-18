<?php
echo "PHP Upload Configuration:\n";
echo "=========================\n\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "file_uploads: " . (ini_get('file_uploads') ? 'ON' : 'OFF') . "\n";
echo "upload_tmp_dir: " . ini_get('upload_tmp_dir') . "\n";
echo "\n";

// Check if upload temp directory exists and is writable
$tmpDir = ini_get('upload_tmp_dir');
if (empty($tmpDir)) {
    $tmpDir = sys_get_temp_dir();
}

echo "Temp directory: $tmpDir\n";
echo "Temp dir exists: " . (file_exists($tmpDir) ? 'YES' : 'NO') . "\n";
echo "Temp dir writable: " . (is_writable($tmpDir) ? 'YES' : 'NO') . "\n";
?>
