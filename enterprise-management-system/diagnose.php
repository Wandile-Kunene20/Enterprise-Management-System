<?php
echo "<h2>Upload System Diagnostic</h2>";

// Check uploads folder
echo "1. Uploads folder exists: " . (is_dir('uploads') ? '✅ YES' : '❌ NO') . "<br>";
echo "2. Uploads folder writable: " . (is_writable('uploads') ? '✅ YES' : '❌ NO') . "<br>";
echo "3. Uploads folder path: " . realpath('uploads') . "<br>";

// Check PHP settings
echo "<br>4. file_uploads: " . (ini_get('file_uploads') ? '✅ ON' : '❌ OFF') . "<br>";
echo "5. upload_max_filesize: " . ini_get('upload_max_filesize') . "<br>";
echo "6. post_max_size: " . ini_get('post_max_size') . "<br>";

// Test create file
$test_file = 'uploads/test_permission.txt';
if (file_put_contents($test_file, 'test')) {
    echo "7. Can create files: ✅ YES<br>";
    unlink($test_file);
} else {
    echo "7. Can create files: ❌ NO<br>";
}

echo "<br><strong>Recommended actions:</strong><br>";
if (!is_writable('uploads')) {
    echo "- Set uploads folder permissions to 755 or 777<br>";
}
if (ini_get('upload_max_filesize') < 5) {
    echo "- Increase upload_max_filesize in php.ini<br>";
}
?>