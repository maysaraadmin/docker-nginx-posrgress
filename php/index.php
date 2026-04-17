<?php
/**
 * Docker Nginx PostgreSQL Setup - PHP Main Interface
 * Secure, production-ready PHP interface for database and nginx management
 */

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self';");

// Configuration
define('DB_API_URL', 'http://api:5000');
define('NGINX_API_URL', 'http://api:5001');
define('APP_NAME', 'Docker Nginx PostgreSQL Setup');
define('APP_VERSION', '2.0.0');

// Helper functions
function makeApiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: ' . APP_NAME . '/' . APP_VERSION
        ]
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => json_decode($response, true),
        'status' => $httpCode
    ];
}

function getStatusBadge($status) {
    $colors = [
        'running' => 'success',
        'healthy' => 'success', 
        'stopped' => 'danger',
        'unhealthy' => 'danger',
        'unknown' => 'warning'
    ];
    
    $color = $colors[$status] ?? 'secondary';
    return "<span class='badge bg-$color'>" . ucfirst($status) . "</span>";
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);
            --danger-gradient: linear-gradient(135deg, #f44336 0%, #e91e63 100%);
        }
        
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .navbar {
            background: var(--primary-gradient);
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
        }
        
        .status-card {
            background: white;
            border-left: 4px solid;
        }
        
        .status-card.postgres { border-left-color: #336791; }
        .status-card.nginx { border-left-color: #009639; }
        .status-card.api { border-left-color: #6f42c1; }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .metric-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .btn-gradient {
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 25px;
            padding: 10px 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner-border {
            width: 1rem;
            height: 1rem;
        }
        
        .alert {
            border: none;
            border-radius: 10px;
        }
        
        .footer {
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(10px);
            margin-top: 50px;
            padding: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-database-locked"></i>
                <?php echo APP_NAME; ?>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="index.php">
                            <i class="bi bi-house"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="database.php">
                            <i class="bi bi-database"></i> Database Admin
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="nginx.php">
                            <i class="bi bi-gear"></i> Nginx Admin
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h1 class="display-4 mb-3">
                            <i class="bi bi-database-locked text-primary"></i>
                            <?php echo APP_NAME; ?>
                        </h1>
                        <p class="lead text-muted">
                            Secure, production-ready Docker setup with Nginx web server and PostgreSQL database
                        </p>
                        <div class="mt-3">
                            <?php echo getStatusBadge('running'); ?>
                            <span class="ms-2 text-muted">Version <?php echo APP_VERSION; ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card status-card postgres">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="bi bi-database text-primary"></i> PostgreSQL
                                </h5>
                                <div id="postgres-status" class="loading">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Checking status...</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="metric-value" id="postgres-connections">-</div>
                                <div class="metric-label">Connections</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card status-card nginx">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="bi bi-gear text-success"></i> Nginx
                                </h5>
                                <div id="nginx-status" class="loading">
                                    <div class="spinner-border spinner-border-sm text-success" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Checking status...</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="metric-value" id="nginx-workers">-</div>
                                <div class="metric-label">Workers</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4 mb-3">
                <div class="card status-card api">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h5 class="card-title mb-1">
                                    <i class="bi bi-code-slash text-info"></i> API Services
                                </h5>
                                <div id="api-status" class="loading">
                                    <div class="spinner-border spinner-border-sm text-info" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Checking status...</span>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="metric-value" id="api-pool">-</div>
                                <div class="metric-label">Pool Size</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- System Metrics -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-cpu"></i> System Metrics
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="metric-value" id="cpu-usage">-</div>
                                    <div class="metric-label">CPU Usage</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="metric-value" id="memory-usage">-</div>
                                    <div class="metric-label">Memory Usage</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="metric-value" id="disk-usage">-</div>
                                    <div class="metric-label">Disk Usage</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <div class="metric-value" id="uptime">-</div>
                                    <div class="metric-label">Uptime</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-lightning"></i> Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <a href="database.php" class="btn btn-gradient w-100">
                                    <i class="bi bi-database"></i> Database Administration
                                </a>
                            </div>
                            <div class="col-md-6 mb-3">
                                <a href="nginx.php" class="btn btn-gradient w-100">
                                    <i class="bi bi-gear"></i> Nginx Administration
                                </a>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-outline-primary w-100" onclick="refreshStatus()">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh Status
                                </button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-outline-success w-100" onclick="testConnections()">
                                    <i class="bi bi-check-circle"></i> Test Connections
                                </button>
                            </div>
                            <div class="col-md-4 mb-3">
                                <button class="btn btn-outline-info w-100" onclick="showLogs()">
                                    <i class="bi bi-file-text"></i> View Logs
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-activity"></i> Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="activity-log">
                            <div class="text-center text-muted">
                                <i class="bi bi-info-circle"></i> Loading recent activity...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h6><?php echo APP_NAME; ?></h6>
                    <p class="text-muted mb-0">Secure, production-ready Docker setup with comprehensive admin interfaces.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <h6>System Information</h6>
                    <p class="text-muted mb-0">
                        Version: <?php echo APP_VERSION; ?><br>
                        PHP: <?php echo PHP_VERSION; ?><br>
                        <small>Last updated: <?php echo date('Y-m-d H:i:s'); ?></small>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        const API_BASE = window.location.origin;
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadAllStatus();
            setInterval(loadAllStatus, 30000); // Refresh every 30 seconds
        });
        
        // Load all status information
        async function loadAllStatus() {
            await Promise.all([
                loadDatabaseStatus(),
                loadNginxStatus(),
                loadSystemMetrics()
            ]);
        }
        
        // Load database status
        async function loadDatabaseStatus() {
            try {
                const response = await fetch(API_BASE + '/api/health');
                const data = await response.json();
                
                document.getElementById('postgres-status').innerHTML = 
                    data.status === 'healthy' ? 
                    '<span class="badge bg-success">Healthy</span>' : 
                    '<span class="badge bg-danger">Unhealthy</span>';
                
                document.getElementById('postgres-connections').textContent = 
                    data.pool_size || 'N/A';
                    
                document.getElementById('api-pool').textContent = 
                    data.pool_size || 'N/A';
                    
                document.getElementById('api-status').innerHTML = 
                    data.status === 'healthy' ? 
                    '<span class="badge bg-success">Running</span>' : 
                    '<span class="badge bg-danger">Error</span>';
                    
            } catch (error) {
                document.getElementById('postgres-status').innerHTML = 
                    '<span class="badge bg-danger">Connection Error</span>';
                document.getElementById('api-status').innerHTML = 
                    '<span class="badge bg-danger">Connection Error</span>';
            }
        }
        
        // Load nginx status
        async function loadNginxStatus() {
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/status');
                const data = await response.json();
                
                document.getElementById('nginx-status').innerHTML = 
                    getStatusBadge(data.status || 'unknown');
                
                document.getElementById('nginx-workers').textContent = 
                    data.worker_processes || 'N/A';
                    
            } catch (error) {
                document.getElementById('nginx-status').innerHTML = 
                    '<span class="badge bg-danger">Connection Error</span>';
            }
        }
        
        // Load system metrics
        async function loadSystemMetrics() {
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/metrics');
                const data = await response.json();
                
                if (data.system) {
                    document.getElementById('cpu-usage').textContent = 
                        (data.system.cpu_percent || 0).toFixed(1) + '%';
                    document.getElementById('memory-usage').textContent = 
                        (data.system.memory_percent || 0).toFixed(1) + '%';
                    document.getElementById('disk-usage').textContent = 
                        (data.system.disk_percent || 0).toFixed(1) + '%';
                }
                
                document.getElementById('uptime').textContent = 
                    data.nginx?.uptime ? Math.floor(data.nginx.uptime / 3600) + 'h' : 'N/A';
                    
            } catch (error) {
                console.error('Failed to load system metrics:', error);
            }
        }
        
        // Helper function for status badge
        function getStatusBadge(status) {
            const colors = {
                'running': 'success',
                'healthy': 'success', 
                'stopped': 'danger',
                'unhealthy': 'danger',
                'unknown': 'warning'
            };
            
            const color = colors[status] || 'secondary';
            return '<span class="badge bg-' + color + '">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>';
        }
        
        // Action functions
        function refreshStatus() {
            document.querySelectorAll('.loading').forEach(el => {
                el.style.display = 'block';
            });
            loadAllStatus();
        }
        
        async function testConnections() {
            const activity = document.getElementById('activity-log');
            activity.innerHTML = '<div class="text-center"><div class="spinner-border"></div> Testing connections...</div>';
            
            const results = [];
            
            // Test database API
            try {
                const response = await fetch(API_BASE + '/api/health');
                results.push({
                    service: 'Database API',
                    status: response.ok ? 'success' : 'error',
                    message: response.ok ? 'Connected successfully' : 'Connection failed'
                });
            } catch (error) {
                results.push({
                    service: 'Database API',
                    status: 'error',
                    message: error.message
                });
            }
            
            // Test nginx API
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/status');
                results.push({
                    service: 'Nginx API',
                    status: response.ok ? 'success' : 'error',
                    message: response.ok ? 'Connected successfully' : 'Connection failed'
                });
            } catch (error) {
                results.push({
                    service: 'Nginx API',
                    status: 'error',
                    message: error.message
                });
            }
            
            // Display results
            let html = '';
            results.forEach(result => {
                const icon = result.status === 'success' ? 'check-circle' : 'x-circle';
                const color = result.status === 'success' ? 'success' : 'danger';
                html += `
                    <div class="alert alert-${color} d-flex align-items-center">
                        <i class="bi bi-${icon} me-2"></i>
                        <div>
                            <strong>${result.service}:</strong> ${result.message}
                        </div>
                    </div>
                `;
            });
            
            activity.innerHTML = html;
        }
        
        function showLogs() {
            alert('Log viewing will be implemented in the database and nginx admin interfaces.');
        }
    </script>
</body>
</html>
