<?php
try {
    $pdo = new PDO('pgsql:host=postgres;dbname=moodle;port=5432', 'moodle_admin', 'moodle_password');
    echo "Database connection: SUCCESS\n";
    $stmt = $pdo->query('SELECT COUNT(*) FROM pg_tables WHERE schemaname = \'public\'');
    $count = $stmt->fetchColumn();
    echo "Tables found: " . $count . "\n";
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
}
?>
