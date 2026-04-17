<?php
/**
 * Nginx Administration Interface - PHP Version
 * Secure web-based Nginx management interface
 */

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self';");

// Configuration
define('NGINX_API_URL', 'http://api:5001');
define('APP_NAME', 'Nginx Administration');

// Helper functions
function makeApiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: NginxAdmin/2.0'
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

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function formatUptime($seconds) {
    $days = floor($seconds / 86400);
    $hours = floor(($seconds % 86400) / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    
    if ($days > 0) {
        return $days . 'd ' . $hours . 'h ' . $minutes . 'm';
    } elseif ($hours > 0) {
        return $hours . 'h ' . $minutes . 'm';
    } else {
        return $minutes . 'm';
    }
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/theme/monokai.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #009639 0%, #007a30 100%);
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
            transform: translateY(-2px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.15);
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
            box-shadow: 0 5px 15px rgba(0, 150, 57, 0.4);
            color: white;
        }
        
        .CodeMirror {
            height: 400px;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .log-viewer {
            height: 400px;
            overflow-y: auto;
            background: #1e1e1e;
            color: #d4d4d4;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 15px;
            border-radius: 10px;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        .status-indicator.online { background-color: #28a745; }
        .status-indicator.offline { background-color: #dc3545; }
        .status-indicator.warning { background-color: #ffc107; }
        
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        
        .metric-card {
            text-align: center;
            padding: 20px;
        }
        
        .metric-value {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .metric-label {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
        }
        
        .alert {
            border: none;
            border-radius: 10px;
        }
        
        .nav-tabs .nav-link {
            border-radius: 10px 10px 0 0;
            border: none;
            background: #f8f9fa;
            color: #495057;
        }
        
        .nav-tabs .nav-link.active {
            background: white;
            color: #009639;
            font-weight: 600;
        }
        
        .control-btn {
            margin: 5px;
        }
        
        .log-line {
            margin: 2px 0;
            padding: 2px 0;
        }
        
        .log-line.error { color: #ff6b6b; }
        .log-line.warning { color: #ffd93d; }
        .log-line.info { color: #6bcf7f; }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-gear"></i>
                Docker Nginx PostgreSQL Setup
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a class="nav-link" href="database.php">
                    <i class="bi bi-database"></i> Database Admin
                </a>
                <a class="nav-link active" href="nginx.php">
                    <i class="bi bi-gear"></i> Nginx Admin
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="h3 mb-2">
                                    <i class="bi bi-gear text-success"></i>
                                    Nginx Administration
                                </h1>
                                <p class="text-muted mb-0">
                                    Secure Nginx server management interface with command injection protection
                                </p>
                            </div>
                            <div>
                                <span id="nginx-status" class="loading">
                                    <div class="spinner-border spinner-border-sm text-success" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Checking status...</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="nginx-version">-</div>
                    <div class="metric-label">Version</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="worker-processes">-</div>
                    <div class="metric-label">Workers</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="memory-usage">-</div>
                    <div class="metric-label">Memory</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="uptime">-</div>
                    <div class="metric-label">Uptime</div>
                </div>
            </div>
        </div>

        <!-- Main Interface -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#control-tab">Control</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#config-tab">Configuration</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#logs-tab">Logs</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#metrics-tab">Metrics</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- Control Tab -->
                            <div class="tab-pane fade show active" id="control-tab">
                                <h5 class="mb-4">Nginx Service Control</h5>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="d-grid">
                                            <button class="btn btn-gradient control-btn" onclick="nginxAction('reload')">
                                                <i class="bi bi-arrow-clockwise"></i> Reload Configuration
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-grid">
                                            <button class="btn btn-gradient control-btn" onclick="nginxAction('restart')">
                                                <i class="bi bi-power"></i> Restart Nginx
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="d-grid">
                                            <button class="btn btn-outline-success control-btn" onclick="nginxAction('start')">
                                                <i class="bi bi-play-circle"></i> Start Nginx
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="d-grid">
                                            <button class="btn btn-outline-danger control-btn" onclick="nginxAction('stop')">
                                                <i class="bi bi-stop-circle"></i> Stop Nginx
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <div class="d-grid">
                                            <button class="btn btn-outline-info control-btn" onclick="testConfiguration()">
                                                <i class="bi bi-check-circle"></i> Test Configuration
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div id="control-result" class="mt-4" style="display: none;">
                                    <h6>Action Result</h6>
                                    <div id="control-content"></div>
                                </div>
                            </div>
                            
                            <!-- Configuration Tab -->
                            <div class="tab-pane fade" id="config-tab">
                                <h5 class="mb-4">Nginx Configuration</h5>
                                
                                <div class="mb-3">
                                    <label for="config-file" class="form-label">Configuration File</label>
                                    <select class="form-select" id="config-file" onchange="loadConfig()">
                                        <option value="nginx.conf">nginx.conf</option>
                                        <option value="default.conf">default.conf</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <div id="config-editor">
                                        <textarea id="nginx-config" class="form-control" rows="20"></textarea>
                                    </div>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-gradient" onclick="saveConfig()">
                                        <i class="bi bi-save"></i> Save Configuration
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="loadConfig()">
                                        <i class="bi bi-arrow-clockwise"></i> Reload
                                    </button>
                                    <button class="btn btn-outline-info" onclick="testConfiguration()">
                                        <i class="bi bi-check-circle"></i> Test
                                    </button>
                                </div>
                                
                                <div id="config-result" class="mt-4" style="display: none;">
                                    <h6>Configuration Result</h6>
                                    <div id="config-content"></div>
                                </div>
                            </div>
                            
                            <!-- Logs Tab -->
                            <div class="tab-pane fade" id="logs-tab">
                                <h5 class="mb-4">Nginx Logs</h5>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label for="log-type" class="form-label">Log Type</label>
                                        <select class="form-select" id="log-type" onchange="loadLogs()">
                                            <option value="access">Access Log</option>
                                            <option value="error">Error Log</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="log-lines" class="form-label">Lines</label>
                                        <select class="form-select" id="log-lines" onchange="loadLogs()">
                                            <option value="50">50 lines</option>
                                            <option value="100">100 lines</option>
                                            <option value="200">200 lines</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <button class="btn btn-gradient" onclick="loadLogs()">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh Logs
                                    </button>
                                    <button class="btn btn-outline-secondary" onclick="clearLogs()">
                                        <i class="bi bi-trash"></i> Clear Display
                                    </button>
                                </div>
                                
                                <div id="log-viewer" class="log-viewer">
                                    <div class="loading">
                                        <div class="spinner-border spinner-border-sm text-light" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                        <span class="ms-2">Loading logs...</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Metrics Tab -->
                            <div class="tab-pane fade" id="metrics-tab">
                                <h5 class="mb-4">System Metrics</h5>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6>CPU Usage</h6>
                                        <div class="progress mb-2">
                                            <div id="cpu-progress" class="progress-bar bg-success" role="progressbar" style="width: 0%">0%</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Memory Usage</h6>
                                        <div class="progress mb-2">
                                            <div id="memory-progress" class="progress-bar bg-warning" role="progressbar" style="width: 0%">0%</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <h6>Disk Usage</h6>
                                        <div class="progress mb-2">
                                            <div id="disk-progress" class="progress-bar bg-info" role="progressbar" style="width: 0%">0%</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Network I/O</h6>
                                        <div class="progress mb-2">
                                            <div id="network-progress" class="progress-bar bg-primary" role="progressbar" style="width: 0%">0%</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="metric-card">
                                            <div class="metric-value" id="total-connections">-</div>
                                            <div class="metric-label">Total Connections</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="metric-card">
                                            <div class="metric-value" id="active-connections">-</div>
                                            <div class="metric-label">Active Connections</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="metric-card">
                                            <div class="metric-value" id="requests-per-second">-</div>
                                            <div class="metric-label">Requests/sec</div>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="metric-card">
                                            <div class="metric-value" id="response-time">-</div>
                                            <div class="metric-label">Avg Response</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button class="btn btn-gradient" onclick="loadMetrics()">
                                        <i class="bi bi-arrow-clockwise"></i> Refresh Metrics
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Sidebar -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-info-circle"></i> Server Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="server-info">
                            <div class="loading">
                                <div class="spinner-border spinner-border-sm text-success" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <span class="ms-2">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-shield-check"></i> Security Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-2">
                            <span class="status-indicator online"></span>
                            <span>Command Injection Protection</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="status-indicator online"></span>
                            <span>Input Validation</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="status-indicator online"></span>
                            <span>Rate Limiting</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="status-indicator online"></span>
                            <span>Secure Logging</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="status-indicator online"></span>
                            <span>CSP Headers</span>
                        </div>
                    </div>
                </div>
                
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-clock-history"></i> Recent Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-actions">
                            <p class="text-muted mb-0">No recent actions</p>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning"></i> Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="quickStatus()">
                                <i class="bi bi-info-circle"></i> Quick Status
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="quickReload()">
                                <i class="bi bi-arrow-clockwise"></i> Quick Reload
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="quickTest()">
                                <i class="bi bi-check-circle"></i> Quick Test
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="viewDashboard()">
                                <i class="bi bi-house"></i> Dashboard
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/nginx/nginx.min.js"></script>
    <script>
        // Initialize CodeMirror
        const configEditor = CodeMirror.fromTextArea(document.getElementById('nginx-config'), {
            mode: 'nginx',
            theme: 'monokai',
            lineNumbers: true,
            autoCloseBrackets: true,
            matchBrackets: true,
            indentUnit: 4,
            tabSize: 4,
            lineWrapping: true
        });
        
        // Global variables
        const API_BASE = window.location.origin;
        let recentActions = [];
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadNginxStatus();
            loadServerInfo();
            loadMetrics();
            setInterval(loadMetrics, 10000); // Refresh metrics every 10 seconds
        });
        
        // Load nginx status
        async function loadNginxStatus() {
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/status');
                const data = await response.json();
                
                if (response.ok) {
                    document.getElementById('nginx-status').innerHTML = 
                        '<span class="status-indicator online"></span>' + data.status;
                    document.getElementById('worker-processes').textContent = 
                        data.worker_processes || '0';
                    document.getElementById('memory-usage').textContent = 
                        data.memory_usage ? formatBytes(data.memory_usage) : 'N/A';
                    document.getElementById('uptime').textContent = 
                        data.uptime ? formatUptime(data.uptime) : 'N/A';
                } else {
                    throw new Error(data.error || 'Failed to load status');
                }
            } catch (error) {
                document.getElementById('nginx-status').innerHTML = 
                    '<span class="status-indicator offline"></span>Connection Error';
                console.error('Failed to load nginx status:', error);
            }
        }
        
        // Load server information
        async function loadServerInfo() {
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/info');
                const data = await response.json();
                
                if (response.ok) {
                    document.getElementById('nginx-version').textContent = 
                        data.version || 'N/A';
                    
                    document.getElementById('server-info').innerHTML = `
                        <p><strong>Version:</strong> ${data.version || 'N/A'}</p>
                        <p><strong>Workers:</strong> ${data.worker_processes || '0'}</p>
                        <p><strong>Config Path:</strong> ${data.config_path || 'N/A'}</p>
                        <p><strong>Status:</strong> <span class="status-indicator online"></span>Running</p>
                    `;
                } else {
                    throw new Error(data.error || 'Failed to load server info');
                }
            } catch (error) {
                document.getElementById('server-info').innerHTML = 
                    '<div class="alert alert-danger">Failed to load server info: ' + error.message + '</div>';
                console.error('Failed to load server info:', error);
            }
        }
        
        // Load metrics
        async function loadMetrics() {
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/metrics');
                const data = await response.json();
                
                if (response.ok && data.system) {
                    // Update progress bars
                    const cpuPercent = data.system.cpu_percent || 0;
                    const memoryPercent = data.system.memory_percent || 0;
                    const diskPercent = data.system.disk_percent || 0;
                    
                    document.getElementById('cpu-progress').style.width = cpuPercent + '%';
                    document.getElementById('cpu-progress').textContent = cpuPercent.toFixed(1) + '%';
                    
                    document.getElementById('memory-progress').style.width = memoryPercent + '%';
                    document.getElementById('memory-progress').textContent = memoryPercent.toFixed(1) + '%';
                    
                    document.getElementById('disk-progress').style.width = diskPercent + '%';
                    document.getElementById('disk-progress').textContent = diskPercent.toFixed(1) + '%';
                    
                    // Update metrics
                    document.getElementById('total-connections').textContent = 
                        data.total_connections || '0';
                    document.getElementById('active-connections').textContent = 
                        data.active_connections || '0';
                    document.getElementById('requests-per-second').textContent = 
                        data.requests_per_second || '0';
                    document.getElementById('response-time').textContent = 
                        data.response_time ? data.response_time + 'ms' : 'N/A';
                }
            } catch (error) {
                console.error('Failed to load metrics:', error);
            }
        }
        
        // Nginx actions
        async function nginxAction(action) {
            const resultDiv = document.getElementById('control-result');
            const resultContent = document.getElementById('control-content');
            
            resultDiv.style.display = 'block';
            resultContent.innerHTML = '<div class="loading"><div class="spinner-border"></div> Executing ' + action + '...</div>';
            
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/' + action, {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    resultContent.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' + data.message + '</div>';
                    addToRecentActions(action, 'success');
                    
                    // Refresh status after action
                    setTimeout(() => {
                        loadNginxStatus();
                        loadServerInfo();
                    }, 1000);
                } else {
                    throw new Error(data.error || 'Action failed');
                }
            } catch (error) {
                resultContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Error: ' + error.message + '</div>';
                addToRecentActions(action, 'error');
                console.error('Nginx action failed:', error);
            }
        }
        
        // Test configuration
        async function testConfiguration() {
            const resultDiv = document.getElementById('control-result');
            const resultContent = document.getElementById('control-content');
            
            resultDiv.style.display = 'block';
            resultContent.innerHTML = '<div class="loading"><div class="spinner-border"></div> Testing configuration...</div>';
            
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/test', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    const alertClass = data.success ? 'success' : 'danger';
                    const icon = data.success ? 'check-circle' : 'x-circle';
                    
                    let html = '<div class="alert alert-' + alertClass + '"><i class="bi bi-' + icon + '"></i> ';
                    html += data.success ? 'Configuration is valid!' : 'Configuration test failed!';
                    html += '</div>';
                    
                    if (data.output) {
                        html += '<div class="mt-2"><strong>Output:</strong><pre>' + data.output + '</pre></div>';
                    }
                    if (data.error) {
                        html += '<div class="mt-2"><strong>Error:</strong><pre>' + data.error + '</pre></div>';
                    }
                    
                    resultContent.innerHTML = html;
                    addToRecentActions('test', data.success ? 'success' : 'error');
                } else {
                    throw new Error(data.error || 'Test failed');
                }
            } catch (error) {
                resultContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Error: ' + error.message + '</div>';
                addToRecentActions('test', 'error');
                console.error('Configuration test failed:', error);
            }
        }
        
        // Load configuration
        async function loadConfig() {
            const configFile = document.getElementById('config-file').value;
            
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/config?file=' + configFile);
                const data = await response.json();
                
                if (response.ok) {
                    configEditor.setValue(data.content || '');
                } else {
                    throw new Error(data.error || 'Failed to load configuration');
                }
            } catch (error) {
                alert('Error loading configuration: ' + error.message);
                console.error('Failed to load configuration:', error);
            }
        }
        
        // Save configuration
        async function saveConfig() {
            const configFile = document.getElementById('config-file').value;
            const content = configEditor.getValue();
            
            const resultDiv = document.getElementById('config-result');
            const resultContent = document.getElementById('config-content');
            
            resultDiv.style.display = 'block';
            resultContent.innerHTML = '<div class="loading"><div class="spinner-border"></div> Saving configuration...</div>';
            
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/config', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        file: configFile,
                        content: content
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    resultContent.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' + data.message + '</div>';
                    addToRecentActions('save config', 'success');
                } else {
                    throw new Error(data.error || 'Save failed');
                }
            } catch (error) {
                resultContent.innerHTML = '<div class="alert alert-danger"><i class="bi bi-x-circle"></i> Error: ' + error.message + '</div>';
                addToRecentActions('save config', 'error');
                console.error('Configuration save failed:', error);
            }
        }
        
        // Load logs
        async function loadLogs() {
            const logType = document.getElementById('log-type').value;
            const lines = document.getElementById('log-lines').value;
            const logViewer = document.getElementById('log-viewer');
            
            logViewer.innerHTML = '<div class="loading"><div class="spinner-border spinner-border-sm text-light"></div><span class="ms-2">Loading logs...</span></div>';
            
            try {
                const response = await fetch(API_BASE + '/nginx-api/nginx/logs?type=' + logType + '&lines=' + lines);
                const data = await response.json();
                
                if (response.ok) {
                    let html = '';
                    if (data.logs && data.logs.length > 0) {
                        data.logs.forEach(line => {
                            let className = 'log-line';
                            if (line.toLowerCase().includes('error')) className += ' error';
                            else if (line.toLowerCase().includes('warn')) className += ' warning';
                            else if (line.toLowerCase().includes('info')) className += ' info';
                            
                            html += '<div class="' + className + '">' + escapeHtml(line) + '</div>';
                        });
                    } else {
                        html = '<div class="text-muted">No log entries found</div>';
                    }
                    
                    logViewer.innerHTML = html;
                } else {
                    throw new Error(data.error || 'Failed to load logs');
                }
            } catch (error) {
                logViewer.innerHTML = '<div class="text-danger">Error loading logs: ' + error.message + '</div>';
                console.error('Failed to load logs:', error);
            }
        }
        
        // Clear logs display
        function clearLogs() {
            document.getElementById('log-viewer').innerHTML = '<div class="text-muted">Log display cleared</div>';
        }
        
        // Helper functions
        function formatBytes(bytes) {
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            for (let i = 0; bytes > 1024 && i < units.length - 1; i++) {
                bytes /= 1024;
            }
            return bytes.toFixed(2) + ' ' + units[i];
        }
        
        function formatUptime(seconds) {
            const days = Math.floor(seconds / 86400);
            const hours = Math.floor((seconds % 86400) / 3600);
            const minutes = Math.floor((seconds % 3600) / 60);
            
            if (days > 0) {
                return days + 'd ' + hours + 'h ' + minutes + 'm';
            } else if (hours > 0) {
                return hours + 'h ' + minutes + 'm';
            } else {
                return minutes + 'm';
            }
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function addToRecentActions(action, status) {
            recentActions.unshift({
                action: action,
                status: status,
                timestamp: new Date().toLocaleTimeString()
            });
            
            recentActions = recentActions.slice(0, 5); // Keep only last 5
            
            let html = '';
            recentActions.forEach(a => {
                const icon = a.status === 'success' ? 'check-circle' : 'x-circle';
                const color = a.status === 'success' ? 'success' : 'danger';
                html += `
                    <div class="mb-2">
                        <small class="text-muted">${a.timestamp}</small><br>
                        <i class="bi bi-${icon} text-${color}"></i>
                        <small>${a.action}</small>
                    </div>
                `;
            });
            
            document.getElementById('recent-actions').innerHTML = html;
        }
        
        // Quick actions
        function quickStatus() {
            loadNginxStatus();
            loadServerInfo();
        }
        
        function quickReload() {
            nginxAction('reload');
        }
        
        function quickTest() {
            testConfiguration();
        }
        
        function viewDashboard() {
            window.location.href = 'index.php';
        }
    </script>
</body>
</html>
