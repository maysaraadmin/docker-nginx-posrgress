<?php
try {
    $pdo = new PDO('pgsql:host=postgres;dbname=moodle', 'moodle_admin', 'SecureP@ssw0rd123!#MoodleDB2024');
    echo "Database connection: SUCCESS\n";
} catch(Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
}

echo "PHP info: " . phpversion() . "\n";
echo "Memory limit: " . ini_get('memory_limit') . "\n";
echo "Max execution time: " . ini_get('max_execution_time') . "\n";
?>
