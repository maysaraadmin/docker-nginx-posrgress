<?php
// Test Redis connection with authentication
try {
    $redis = new Redis();
    $redis->connect('redis', 6379);
    $redis->auth('RedisSecureP@ss789!@#2024');
    
    echo "Redis Connection: SUCCESS\n";
    
    // Test set/get
    $redis->set('test_key', 'test_value', 10);
    $value = $redis->get('test_key');
    echo "Redis Set/Get Test: " . ($value === 'test_value' ? 'SUCCESS' : 'FAILED') . "\n";
    
    // Test in database 1 (sessions)
    $redis->select(1);
    $redis->set('session_test', 'session_data', 10);
    $session_value = $redis->get('session_test');
    echo "Redis Session DB Test: " . ($session_value === 'session_data' ? 'SUCCESS' : 'FAILED') . "\n";
    
} catch (Exception $e) {
    echo "Redis Error: " . $e->getMessage() . "\n";
}
?>
