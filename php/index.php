<?php
/**
 * Docker Nginx PostgreSQL Setup - PHP Main Interface
 * Moodle Learning Management System Platform
 */

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; font-src 'self' https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'self'; form-action 'self';");

// Configuration
define('APP_NAME', 'Docker Nginx PostgreSQL Setup');
define('APP_VERSION', '2.0.0');

// Handle AJAX requests
if (isset($_GET['ajax']) && $_GET['ajax'] === 'status') {
    // Simple authentication check (in production, use proper session/auth)
    $auth_token = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    $expected_token = getenv('STATUS_AUTH_TOKEN') ?: 'moodle-status-token-2024';
    
    if ($auth_token !== $expected_token) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    header('Content-Type: application/json');
    echo json_encode(['services' => getServiceStatus()]);
    exit;
}


// Get service status
function getServiceStatus() {
    $services = [];
    
    // Check PostgreSQL
    $postgres_status = checkPostgresHealth();
    $services['postgres'] = [
        'name' => 'PostgreSQL', 
        'status' => $postgres_status ? 'online' : 'offline', 
        'icon' => 'database'
    ];
    
    // Check Redis
    $redis_status = checkRedisHealth();
    $services['redis'] = [
        'name' => 'Redis', 
        'status' => $redis_status ? 'online' : 'offline', 
        'icon' => 'memory'
    ];
    
    // Check Nginx (always online if PHP is running)
    $services['nginx'] = [
        'name' => 'Nginx', 
        'status' => 'online', 
        'icon' => 'server'
    ];
    
    // Check PHP-FPM (always online if this script runs)
    $services['php'] = [
        'name' => 'PHP-FPM', 
        'status' => 'online', 
        'icon' => 'code'
    ];
    
    return $services;
}

function checkPostgresHealth() {
    try {
        $host = 'postgres';
        $port = 5432;
        $timeout = 2;
        
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket) {
            fclose($socket);
            return true;
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

function checkRedisHealth() {
    try {
        $host = 'redis';
        $port = 6379;
        $timeout = 2;
        $password = getenv('REDIS_PASSWORD') ?: 'RedisSecureP@ss789!@#2024';
        
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket) {
            // Try to authenticate
            fwrite($socket, "AUTH $password\r\n");
            $response = fgets($socket, 1024);
            fclose($socket);
            
            $isAuthenticated = strpos($response, '+OK') !== false;
            if (!$isAuthenticated) {
                error_log("Redis authentication failed: " . trim($response));
            }
            return $isAuthenticated;
        }
        error_log("Redis connection failed: $errno - $errstr");
        return false;
    } catch (Exception $e) {
        error_log("Redis health check exception: " . $e->getMessage());
        return false;
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            padding-top: 50px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            background: rgba(255,255,255,0.9);
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 25px;
            padding: 10px 30px;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-online {
            background: #28a745;
            color: white;
        }
        .status-offline {
            background: #dc3545;
            color: white;
        }
        .status-warning {
            background: #ffc107;
            color: #000;
        }
        .service-card {
            transition: transform 0.3s ease;
        }
        .service-card:hover {
            transform: translateY(-5px);
        }
        .moodle-card {
            background: linear-gradient(135deg, #ff6b6b 0%, #feca57 100%);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header text-center">
                        <h1 class="mb-0"><?php echo APP_NAME; ?></h1>
                        <p class="mb-0 mt-2">Moodle Learning Management System Platform</p>
                    </div>
                    <div class="card-body">
                        <!-- Moodle Access -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <div class="card moodle-card service-card">
                                    <div class="card-body text-center">
                                        <h4 class="card-title">
                                            <i class="bi bi-mortarboard-fill me-2"></i>
                                            Moodle LMS
                                        </h4>
                                        <p class="card-text">Learning Management System for Education</p>
                                        <a href="/moodle" class="btn btn-light btn-lg">
                                            <i class="bi bi-arrow-right-circle me-2"></i>
                                            Access Moodle
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Service Status -->
                        <div class="row mb-4">
                            <div class="col-md-12">
                                <h4 class="mb-3">
                                    <i class="bi bi-activity me-2"></i>
                                    Service Status
                                </h4>
                                <div class="row">
                                    <?php
                                    $services = getServiceStatus();
                                    foreach ($services as $serviceId => $service):
                                    ?>
                                    <div class="col-md-3 mb-3">
                                        <div class="card service-card h-100" data-service="<?php echo $serviceId; ?>">
                                            <div class="card-body text-center">
                                                <div class="mb-2">
                                                    <i class="bi bi-<?php echo $service['icon']; ?> fs-2"></i>
                                                </div>
                                                <h6><?php echo $service['name']; ?></h6>
                                                <span class="status-badge status-<?php echo $service['status']; ?>">
                                                    <?php echo ucfirst($service['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Services -->
                        <div class="row">
                            <div class="col-md-12">
                                <h4 class="mb-3">
                                    <i class="bi bi-tools me-2"></i>
                                    Additional Services
                                </h4>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="card service-card h-100">
                                            <div class="card-body text-center">
                                                <div class="mb-2">
                                                    <i class="bi bi-database-fill-gear fs-2"></i>
                                                </div>
                                                <h6>Adminer Database Manager</h6>
                                                <p class="card-text">Web-based database administration</p>
                                                <a href="http://localhost:8080" target="_blank" class="btn btn-outline-primary">
                                                    <i class="bi bi-box-arrow-up-right me-1"></i>
                                                    Open Adminer
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- System Information -->
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="mb-0">
                                            <i class="bi bi-info-circle me-2"></i>
                                            System Information
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Platform:</strong> Docker Nginx PostgreSQL PHP</p>
                                                <p><strong>Version:</strong> <?php echo APP_VERSION; ?></p>
                                                <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <p><strong>Database:</strong> PostgreSQL</p>
                                                <p><strong>Cache:</strong> Redis</p>
                                                <p><strong>Web Server:</strong> Nginx</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh service status every 30 seconds using AJAX
        function updateServiceStatus() {
            fetch('/php/index.php?ajax=status', {
                headers: {
                    'X-Auth-Token': '<?php echo getenv('STATUS_AUTH_TOKEN') ?: 'moodle-status-token-2024'; ?>'
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Authentication failed');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.services) {
                        Object.keys(data.services).forEach(serviceId => {
                            const service = data.services[serviceId];
                            const badge = document.querySelector(`[data-service="${serviceId}"] .status-badge`);
                            if (badge) {
                                badge.className = `status-badge status-${service.status}`;
                                badge.textContent = service.charAt(0).toUpperCase() + service.status.slice(1);
                            }
                        });
                    }
                })
                .catch(error => console.error('Error updating service status:', error));
        }
        
        // Update status every 30 seconds
        setInterval(updateServiceStatus, 30000);
        
        // Initial status update after page load
        setTimeout(updateServiceStatus, 1000);
    </script>
</body>
</html>
