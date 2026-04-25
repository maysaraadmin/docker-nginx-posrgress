<?php
// Test Redis connection with IP
$start_time = microtime(true);

try {
    $redis = new Redis();
    $redis->connect('172.19.0.3', 6379, 5);
    
    // Test basic operations
    $redis->set('test_key', 'test_value');
    $result = $redis->get('test_key');
    
    $redis->close();
    
    $end_time = microtime(true);
    $execution_time = ($end_time - $start_time) * 1000; // Convert to milliseconds
    
    echo "Redis connection: SUCCESS\n";
    echo "Test operation: $result\n";
    echo "Execution time: " . round($execution_time, 2) . " ms\n";
    
} catch (Exception $e) {
    echo "Redis connection: FAILED - " . $e->getMessage() . "\n";
}
?>
