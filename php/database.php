<?php
/**
 * Database Administration Interface - PHP Version
 * Secure web-based database management interface
 */

// Security headers
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-ancestors 'self';");

// Configuration
define('DB_API_URL', 'http://api:5000');
define('APP_NAME', 'Database Administration');

// Helper functions
function makeApiRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'User-Agent: DatabaseAdmin/2.0'
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
            --primary-gradient: linear-gradient(135deg, #336791 0%, #2c5282 100%);
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
            box-shadow: 0 5px 15px rgba(51, 103, 145, 0.4);
            color: white;
        }
        
        .table-responsive {
            border-radius: 10px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .CodeMirror {
            height: 300px;
            border-radius: 10px;
            font-size: 14px;
        }
        
        .query-result {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        
        .status-indicator.online { background-color: #28a745; }
        .status-indicator.offline { background-color: #dc3545; }
        
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
            color: #336791;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-database-locked"></i>
                Docker Nginx PostgreSQL Setup
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="index.php">
                    <i class="bi bi-house"></i> Dashboard
                </a>
                <a class="nav-link active" href="database.php">
                    <i class="bi bi-database"></i> Database Admin
                </a>
                <a class="nav-link" href="nginx.php">
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
                                    <i class="bi bi-database text-primary"></i>
                                    Database Administration
                                </h1>
                                <p class="text-muted mb-0">
                                    Secure database management interface with SQL injection protection
                                </p>
                            </div>
                            <div>
                                <span id="connection-status" class="loading">
                                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Checking connection...</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Database Info Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="db-size">-</div>
                    <div class="metric-label">Database Size</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="table-count">-</div>
                    <div class="metric-label">Tables</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="connection-count">-</div>
                    <div class="metric-label">Connections</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card metric-card">
                    <div class="metric-value" id="pool-size">-</div>
                    <div class="metric-label">Pool Size</div>
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
                                <a class="nav-link active" data-bs-toggle="tab" href="#query-tab">SQL Query</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#tables-tab">Tables</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#create-tab">Create Table</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <!-- SQL Query Tab -->
                            <div class="tab-pane fade show active" id="query-tab">
                                <h5 class="mb-3">Execute SQL Query</h5>
                                <form id="query-form">
                                    <div class="mb-3">
                                        <label for="sql-query" class="form-label">SQL Query</label>
                                        <textarea id="sql-query" class="form-control" rows="6" placeholder="Enter your SQL query here..."></textarea>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-gradient">
                                            <i class="bi bi-play-fill"></i> Execute Query
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="clearQuery()">
                                            <i class="bi bi-x-circle"></i> Clear
                                        </button>
                                        <button type="button" class="btn btn-outline-info" onclick="loadSampleQuery()">
                                            <i class="bi bi-lightbulb"></i> Sample Query
                                        </button>
                                    </div>
                                </form>
                                
                                <div id="query-result" class="mt-4" style="display: none;">
                                    <h6>Query Results</h6>
                                    <div id="result-content"></div>
                                </div>
                            </div>
                            
                            <!-- Tables Tab -->
                            <div class="tab-pane fade" id="tables-tab">
                                <h5 class="mb-3">Database Tables</h5>
                                <div id="tables-list" class="loading">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <span class="ms-2">Loading tables...</span>
                                </div>
                            </div>
                            
                            <!-- Create Table Tab -->
                            <div class="tab-pane fade" id="create-tab">
                                <h5 class="mb-3">Create New Table</h5>
                                <form id="create-table-form">
                                    <div class="mb-3">
                                        <label for="table-name" class="form-label">Table Name</label>
                                        <input type="text" id="table-name" class="form-control" placeholder="Enter table name..." required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Columns</label>
                                        <div id="columns-container">
                                            <div class="row column-row mb-2">
                                                <div class="col-md-4">
                                                    <input type="text" class="form-control column-name" placeholder="Column name..." required>
                                                </div>
                                                <div class="col-md-3">
                                                    <select class="form-select column-type">
                                                        <option value="INTEGER">INTEGER</option>
                                                        <option value="VARCHAR">VARCHAR</option>
                                                        <option value="TEXT">TEXT</option>
                                                        <option value="BOOLEAN">BOOLEAN</option>
                                                        <option value="TIMESTAMP">TIMESTAMP</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input column-primary" type="checkbox">
                                                        <label class="form-check-label">Primary</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input column-not-null" type="checkbox">
                                                        <label class="form-check-label">Not Null</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-1">
                                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeColumn(this)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="addColumn()">
                                            <i class="bi bi-plus-circle"></i> Add Column
                                        </button>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button type="submit" class="btn btn-gradient">
                                            <i class="bi bi-plus-square"></i> Create Table
                                        </button>
                                        <button type="button" class="btn btn-outline-secondary" onclick="resetCreateForm()">
                                            <i class="bi bi-x-circle"></i> Reset
                                        </button>
                                    </div>
                                </form>
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
                            <i class="bi bi-info-circle"></i> Database Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="db-info">
                            <div class="loading">
                                <div class="spinner-border spinner-border-sm text-primary" role="status">
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
                            <span>SQL Injection Protection</span>
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
                            <span>Connection Pooling</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="status-indicator online"></span>
                            <span>Secure Logging</span>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i class="bi bi-clock-history"></i> Recent Queries
                        </h6>
                    </div>
                    <div class="card-body">
                        <div id="recent-queries">
                            <p class="text-muted mb-0">No recent queries</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/codemirror.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.2/mode/sql/sql.min.js"></script>
    <script>
        // Initialize CodeMirror
        const editor = CodeMirror.fromTextArea(document.getElementById('sql-query'), {
            mode: 'text/x-sql',
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
        let recentQueries = [];
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            loadDatabaseInfo();
            loadTables();
        });
        
        // Load database information
        async function loadDatabaseInfo() {
            try {
                const response = await fetch(API_BASE + '/api/info');
                const data = await response.json();
                
                if (response.ok) {
                    document.getElementById('db-size').textContent = data.size || 'N/A';
                    document.getElementById('table-count').textContent = data.table_count || '0';
                    document.getElementById('connection-count').textContent = data.connections || '0';
                    document.getElementById('pool-size').textContent = data.pool_active || '0';
                    
                    document.getElementById('db-info').innerHTML = `
                        <p><strong>Version:</strong> ${data.version || 'N/A'}</p>
                        <p><strong>Database:</strong> ${data.database || 'N/A'}</p>
                        <p><strong>Size:</strong> ${data.size || 'N/A'}</p>
                        <p><strong>Connections:</strong> ${data.connections || '0'}</p>
                    `;
                    
                    document.getElementById('connection-status').innerHTML = 
                        '<span class="status-indicator online"></span>Connected';
                } else {
                    throw new Error(data.error || 'Failed to load database info');
                }
            } catch (error) {
                document.getElementById('connection-status').innerHTML = 
                    '<span class="status-indicator offline"></span>Connection Error';
                console.error('Failed to load database info:', error);
            }
        }
        
        // Load tables
        async function loadTables() {
            try {
                const response = await fetch(API_BASE + '/api/tables');
                const data = await response.json();
                
                if (response.ok) {
                    let html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Table Name</th><th>Type</th><th>Actions</th></tr></thead><tbody>';
                    
                    data.tables.forEach(table => {
                        html += `
                            <tr>
                                <td>${table.table_name}</td>
                                <td><span class="badge bg-info">${table.table_type}</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="viewTable('${table.table_name}')">
                                        <i class="bi bi-eye"></i> View
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="dropTable('${table.table_name}')">
                                        <i class="bi bi-trash"></i> Drop
                                    </button>
                                </td>
                            </tr>
                        `;
                    });
                    
                    html += '</tbody></table></div>';
                    document.getElementById('tables-list').innerHTML = html;
                } else {
                    throw new Error(data.error || 'Failed to load tables');
                }
            } catch (error) {
                document.getElementById('tables-list').innerHTML = 
                    '<div class="alert alert-danger">Failed to load tables: ' + error.message + '</div>';
                console.error('Failed to load tables:', error);
            }
        }
        
        // Execute SQL query
        document.getElementById('query-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const query = editor.getValue().trim();
            if (!query) {
                alert('Please enter a SQL query');
                return;
            }
            
            const resultDiv = document.getElementById('query-result');
            const resultContent = document.getElementById('result-content');
            
            resultDiv.style.display = 'block';
            resultContent.innerHTML = '<div class="loading"><div class="spinner-border"></div> Executing query...</div>';
            
            try {
                const response = await fetch(API_BASE + '/api/query', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ query: query })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    let html = '';
                    
                    if (data.query_type === 'SELECT' && data.results) {
                        html += '<div class="query-result"><div class="table-responsive"><table class="table table-striped table-sm">';
                        
                        // Headers
                        if (data.results.length > 0) {
                            html += '<thead><tr>';
                            Object.keys(data.results[0]).forEach(key => {
                                html += '<th>' + key + '</th>';
                            });
                            html += '</tr></thead><tbody>';
                            
                            // Data rows
                            data.results.forEach(row => {
                                html += '<tr>';
                                Object.values(row).forEach(value => {
                                    html += '<td>' + (value === null ? '<em>NULL</em>' : value) + '</td>';
                                });
                                html += '</tr>';
                            });
                        }
                        
                        html += '</tbody></table></div></div>';
                        html += '<p class="mt-2"><strong>Row Count:</strong> ' + data.row_count + '</p>';
                    } else {
                        html = '<div class="alert alert-success">';
                        html += '<strong>Query executed successfully!</strong><br>';
                        html += 'Query Type: ' + data.query_type + '<br>';
                        if (data.affected_rows !== undefined) {
                            html += 'Affected Rows: ' + data.affected_rows;
                        }
                        html += '</div>';
                    }
                    
                    resultContent.innerHTML = html;
                    
                    // Add to recent queries
                    addToRecentQueries(query, 'success');
                    
                } else {
                    throw new Error(data.error || 'Query execution failed');
                }
            } catch (error) {
                resultContent.innerHTML = '<div class="alert alert-danger"><strong>Error:</strong> ' + error.message + '</div>';
                addToRecentQueries(query, 'error');
                console.error('Query execution failed:', error);
            }
        });
        
        // Create table
        document.getElementById('create-table-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const tableName = document.getElementById('table-name').value.trim();
            const columns = [];
            
            document.querySelectorAll('.column-row').forEach(row => {
                const name = row.querySelector('.column-name').value.trim();
                const type = row.querySelector('.column-type').value;
                const primary = row.querySelector('.column-primary').checked;
                const notNull = row.querySelector('.column-not-null').checked;
                
                if (name) {
                    columns.push({ name, type, primary_key: primary, not_null: notNull });
                }
            });
            
            if (!tableName || columns.length === 0) {
                alert('Please provide table name and at least one column');
                return;
            }
            
            try {
                const response = await fetch(API_BASE + '/api/tables', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        table_name: tableName,
                        columns: columns
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    alert('Table "' + tableName + '" created successfully!');
                    resetCreateForm();
                    loadTables();
                    loadDatabaseInfo();
                } else {
                    throw new Error(data.error || 'Table creation failed');
                }
            } catch (error) {
                alert('Error creating table: ' + error.message);
                console.error('Table creation failed:', error);
            }
        });
        
        // Helper functions
        function clearQuery() {
            editor.setValue('');
            document.getElementById('query-result').style.display = 'none';
        }
        
        function loadSampleQuery() {
            editor.setValue('SELECT table_name, table_type FROM information_schema.tables WHERE table_schema = \'public\' ORDER BY table_name;');
        }
        
        function addColumn() {
            const container = document.getElementById('columns-container');
            const newRow = document.createElement('div');
            newRow.className = 'row column-row mb-2';
            newRow.innerHTML = `
                <div class="col-md-4">
                    <input type="text" class="form-control column-name" placeholder="Column name..." required>
                </div>
                <div class="col-md-3">
                    <select class="form-select column-type">
                        <option value="INTEGER">INTEGER</option>
                        <option value="VARCHAR">VARCHAR</option>
                        <option value="TEXT">TEXT</option>
                        <option value="BOOLEAN">BOOLEAN</option>
                        <option value="TIMESTAMP">TIMESTAMP</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input column-primary" type="checkbox">
                        <label class="form-check-label">Primary</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="form-check">
                        <input class="form-check-input column-not-null" type="checkbox">
                        <label class="form-check-label">Not Null</label>
                    </div>
                </div>
                <div class="col-md-1">
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeColumn(this)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            `;
            container.appendChild(newRow);
        }
        
        function removeColumn(button) {
            button.closest('.column-row').remove();
        }
        
        function resetCreateForm() {
            document.getElementById('create-table-form').reset();
            const container = document.getElementById('columns-container');
            container.innerHTML = container.querySelector('.column-row').outerHTML;
        }
        
        async function viewTable(tableName) {
            const query = `SELECT * FROM "${tableName}" LIMIT 100;`;
            editor.setValue(query);
            
            // Switch to query tab and execute
            document.querySelector('[href="#query-tab"]').click();
            document.getElementById('query-form').dispatchEvent(new Event('submit'));
        }
        
        async function dropTable(tableName) {
            if (!confirm('Are you sure you want to drop table "' + tableName + '"? This action cannot be undone.')) {
                return;
            }
            
            try {
                const response = await fetch(API_BASE + '/api/tables/' + tableName, {
                    method: 'DELETE'
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    alert('Table "' + tableName + '" dropped successfully!');
                    loadTables();
                    loadDatabaseInfo();
                } else {
                    throw new Error(data.error || 'Table drop failed');
                }
            } catch (error) {
                alert('Error dropping table: ' + error.message);
                console.error('Table drop failed:', error);
            }
        }
        
        function addToRecentQueries(query, status) {
            recentQueries.unshift({
                query: query.substring(0, 50) + (query.length > 50 ? '...' : ''),
                status: status,
                timestamp: new Date().toLocaleTimeString()
            });
            
            recentQueries = recentQueries.slice(0, 5); // Keep only last 5
            
            let html = '';
            recentQueries.forEach(q => {
                const icon = q.status === 'success' ? 'check-circle' : 'x-circle';
                const color = q.status === 'success' ? 'success' : 'danger';
                html += `
                    <div class="mb-2">
                        <small class="text-muted">${q.timestamp}</small><br>
                        <i class="bi bi-${icon} text-${color}"></i>
                        <small>${q.query}</small>
                    </div>
                `;
            });
            
            document.getElementById('recent-queries').innerHTML = html;
        }
    </script>
</body>
</html>
