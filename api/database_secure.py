#!/usr/bin/env python3
"""
Secure PostgreSQL Database API for Docker Nginx PostgreSQL Setup
Provides REST API endpoints for database operations with security fixes
"""

import os
import psycopg2
import psycopg2.extras
import psycopg2.pool
from flask import Flask, request, jsonify
from flask_cors import CORS
from flask_limiter import Limiter
from flask_limiter.util import get_remote_address
import json
from datetime import datetime
import logging
from security import SQLValidator, InputValidator, rate_limit, generate_secure_token

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s %(name)s %(levelname)s %(message)s',
    handlers=[
        logging.FileHandler(os.getenv('LOG_FILE', '/app/logs/database.log')),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)

app = Flask(__name__)
CORS(app)

# Rate limiting
limiter = Limiter(
    app=app,
    key_func=get_remote_address,
    default_limits=[os.getenv('RATE_LIMIT', '100/hour')]
)

# Database connection pool configuration
DB_CONFIG = {
    'host': os.getenv('POSTGRES_HOST', 'postgres'),
    'port': int(os.getenv('POSTGRES_PORT', 5432)),
    'database': os.getenv('POSTGRES_DB', 'appdb'),
    'user': os.getenv('POSTGRES_USER', 'appuser'),
    'password': os.getenv('POSTGRES_PASSWORD', 'securepassword')
}

# Create connection pool
try:
    connection_pool = psycopg2.pool.ThreadedConnectionPool(
        minconn=2,
        maxconn=10,
        **DB_CONFIG
    )
    logger.info("Database connection pool created successfully")
except Exception as e:
    logger.error(f"Failed to create connection pool: {e}")
    connection_pool = None

def get_db_connection():
    """Get database connection from pool"""
    if not connection_pool:
        logger.error("Connection pool not available")
        return None
    
    try:
        conn = connection_pool.getconn()
        return conn
    except Exception as e:
        logger.error(f"Failed to get database connection: {e}")
        return None

def release_db_connection(conn):
    """Release database connection back to pool"""
    if conn and connection_pool:
        try:
            connection_pool.putconn(conn)
        except Exception as e:
            logger.error(f"Failed to release connection: {e}")

@app.route('/')
def api_home():
    """API home page with documentation"""
    return jsonify({
        'name': 'Secure PostgreSQL Database API',
        'version': '2.0.0',
        'status': 'running',
        'security': 'enabled',
        'features': ['sql_injection_protection', 'rate_limiting', 'connection_pooling'],
        'endpoints': {
            'GET /': 'API information',
            'GET /health': 'Health check',
            'GET /tables': 'List all tables',
            'GET /tables/<table_name>': 'Get table structure',
            'GET /query': 'Execute SELECT query (POST for other queries)',
            'POST /query': 'Execute any SQL query',
            'GET /info': 'Database information',
            'POST /tables': 'Create new table',
            'DELETE /tables/<table_name>': 'Drop table'
        }
    })

@app.route('/health')
@limiter.limit("60/minute")
def health_check():
    """Health check endpoint"""
    conn = get_db_connection()
    if conn:
        release_db_connection(conn)
        return jsonify({
            'status': 'healthy', 
            'database': 'connected',
            'pool_size': connection_pool.minconn if connection_pool else 0,
            'timestamp': datetime.now().isoformat()
        })
    else:
        return jsonify({
            'status': 'unhealthy', 
            'database': 'disconnected',
            'timestamp': datetime.now().isoformat()
        }), 500

@app.route('/info')
@limiter.limit("30/minute")
def database_info():
    """Get database information"""
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        
        # Get PostgreSQL version
        cursor.execute("SELECT version()")
        version_info = cursor.fetchone()
        
        # Get database size
        cursor.execute(f"""
            SELECT pg_size_pretty(pg_database_size('{DB_CONFIG['database']}')) as size
        """)
        size_info = cursor.fetchone()
        
        # Get connection count
        cursor.execute("SELECT count(*) as connections FROM pg_stat_activity")
        connection_info = cursor.fetchone()
        
        # Get table count
        cursor.execute("""
            SELECT count(*) as table_count 
            FROM information_schema.tables 
            WHERE table_schema = 'public'
        """)
        table_info = cursor.fetchone()
        
        cursor.close()
        release_db_connection(conn)
        
        return jsonify({
            'version': version_info['version'],
            'database': DB_CONFIG['database'],
            'size': size_info['size'],
            'connections': connection_info['connections'],
            'table_count': table_info['table_count'],
            'pool_active': connection_pool.minconn if connection_pool else 0,
            'timestamp': datetime.now().isoformat()
        })
        
    except Exception as e:
        release_db_connection(conn)
        logger.error(f"Database info error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/tables')
@limiter.limit("30/minute")
def list_tables():
    """List all tables in the database"""
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        cursor.execute("""
            SELECT table_name, table_type 
            FROM information_schema.tables 
            WHERE table_schema = 'public' 
            ORDER BY table_name
        """)
        tables = cursor.fetchall()
        cursor.close()
        release_db_connection(conn)
        
        return jsonify({'tables': [dict(table) for table in tables]})
        
    except Exception as e:
        release_db_connection(conn)
        logger.error(f"List tables error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/tables/<table_name>')
@limiter.limit("30/minute")
def get_table_structure(table_name):
    """Get table structure"""
    # Validate table name
    is_valid, error = InputValidator.validate_table_name(table_name)
    if not is_valid:
        return jsonify({'error': error}), 400
    
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        
        # Use parameterized query to prevent injection
        cursor.execute("""
            SELECT 
                column_name, 
                data_type, 
                is_nullable, 
                column_default,
                character_maximum_length
            FROM information_schema.columns 
            WHERE table_name = %s AND table_schema = 'public'
            ORDER BY ordinal_position
        """, (table_name,))
        columns = cursor.fetchall()
        
        # Get row count safely
        cursor.execute("SELECT count(*) as row_count FROM information_schema.tables WHERE table_name = %s AND table_schema = 'public'", (table_name,))
        table_exists = cursor.fetchone()['row_count'] > 0
        
        if not table_exists:
            cursor.close()
            release_db_connection(conn)
            return jsonify({'error': 'Table not found'}), 404
        
        cursor.execute(f"SELECT count(*) as row_count FROM \"{table_name}\"")
        row_count = cursor.fetchone()['row_count']
        
        cursor.close()
        release_db_connection(conn)
        
        return jsonify({
            'table_name': table_name,
            'columns': [dict(col) for col in columns],
            'row_count': row_count
        })
        
    except Exception as e:
        release_db_connection(conn)
        logger.error(f"Table structure error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/query', methods=['GET', 'POST'])
@limiter.limit("60/minute")
def execute_query():
    """Execute SQL query with security validation"""
    if request.method == 'GET':
        query = request.args.get('q', '')
    else:
        query = request.json.get('query', '') if request.json else ''
    
    if not query:
        return jsonify({'error': 'No query provided'}), 400
    
    # Validate query for SQL injection
    is_safe, error = SQLValidator.is_safe_query(query)
    if not is_safe:
        logger.warning(f"SQL injection attempt: {query[:100]}...")
        return jsonify({'error': f'Query validation failed: {error}'}), 400
    
    # Sanitize query
    query = SQLValidator.sanitize_query(query)
    
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        
        # Log the query (without sensitive data)
        logger.info(f"Executing query: {query[:100]}...")
        
        cursor.execute(query)
        
        # Handle different query types
        query_upper = query.upper().strip()
        if query_upper.startswith('SELECT') or query_upper.startswith('SHOW') or query_upper.startswith('DESCRIBE'):
            results = cursor.fetchall()
            response = {
                'query': query,
                'results': [dict(row) for row in results],
                'row_count': len(results),
                'query_type': 'SELECT'
            }
        else:
            conn.commit()
            response = {
                'query': query,
                'affected_rows': cursor.rowcount,
                'query_type': 'MODIFICATION'
            }
        
        cursor.close()
        release_db_connection(conn)
        return jsonify(response)
        
    except Exception as e:
        conn.rollback()
        release_db_connection(conn)
        logger.error(f"Query execution error: {e}")
        return jsonify({'error': str(e), 'query': query}), 500

@app.route('/tables', methods=['POST'])
@limiter.limit("10/minute")
def create_table():
    """Create new table with validation"""
    data = request.json
    if not data or 'table_name' not in data or 'columns' not in data:
        return jsonify({'error': 'table_name and columns required'}), 400
    
    table_name = data['table_name']
    columns = data['columns']
    
    # Validate table name
    is_valid, error = InputValidator.validate_table_name(table_name)
    if not is_valid:
        return jsonify({'error': error}), 400
    
    # Validate columns
    if not columns or not isinstance(columns, list):
        return jsonify({'error': 'columns must be a non-empty list'}), 400
    
    if len(columns) > 100:  # Reasonable limit
        return jsonify({'error': 'Too many columns (max 100)'}), 400
    
    # Build CREATE TABLE statement with validation
    column_definitions = []
    for i, col in enumerate(columns):
        if not isinstance(col, dict):
            return jsonify({'error': f'Column {i+1} must be an object'}), 400
        
        if 'name' not in col or 'type' not in col:
            return jsonify({'error': f'Column {i+1} must have name and type'}), 400
        
        # Validate column name
        is_valid, error = InputValidator.validate_column_name(col['name'])
        if not is_valid:
            return jsonify({'error': f'Column {i+1}: {error}'}), 400
        
        # Validate data type
        is_valid, error = InputValidator.validate_sql_type(col['type'])
        if not is_valid:
            return jsonify({'error': f'Column {i+1}: {error}'}), 400
        
        col_def = f"\"{col['name']}\" {col['type']}"
        if col.get('primary_key'):
            col_def += " PRIMARY KEY"
        if col.get('not_null'):
            col_def += " NOT NULL"
        if col.get('default'):
            # Basic validation for default values
            default_val = str(col['default'])
            if len(default_val) > 255:
                return jsonify({'error': f'Column {i+1}: Default value too long'}), 400
            col_def += f" DEFAULT {col['default']}"
        
        column_definitions.append(col_def)
    
    # Create safe SQL statement
    create_sql = f'CREATE TABLE "{table_name}" ({", ".join(column_definitions)})'
    
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor()
        
        # Validate the SQL one more time
        is_safe, error = SQLValidator.is_safe_query(create_sql)
        if not is_safe:
            return jsonify({'error': f'SQL validation failed: {error}'}), 400
        
        cursor.execute(create_sql)
        conn.commit()
        cursor.close()
        release_db_connection(conn)
        
        logger.info(f"Table {table_name} created successfully")
        return jsonify({
            'message': f'Table {table_name} created successfully',
            'table_name': table_name,
            'columns': columns
        })
        
    except Exception as e:
        conn.rollback()
        release_db_connection(conn)
        logger.error(f"Table creation error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/tables/<table_name>', methods=['DELETE'])
@limiter.limit("10/minute")
def drop_table(table_name):
    """Drop table with validation"""
    # Validate table name
    is_valid, error = InputValidator.validate_table_name(table_name)
    if not is_valid:
        return jsonify({'error': error}), 400
    
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor()
        
        # Create safe SQL statement
        drop_sql = f'DROP TABLE IF EXISTS "{table_name}"'
        
        cursor.execute(drop_sql)
        conn.commit()
        cursor.close()
        release_db_connection(conn)
        
        logger.info(f"Table {table_name} dropped successfully")
        return jsonify({'message': f'Table {table_name} dropped successfully'})
        
    except Exception as e:
        conn.rollback()
        release_db_connection(conn)
        logger.error(f"Table drop error: {e}")
        return jsonify({'error': str(e)}), 500

@app.route('/tables/<table_name>/data', methods=['GET'])
@limiter.limit("30/minute")
def get_table_data(table_name):
    """Get table data with pagination and validation"""
    # Validate table name
    is_valid, error = InputValidator.validate_table_name(table_name)
    if not is_valid:
        return jsonify({'error': error}), 400
    
    page = min(int(request.args.get('page', 1)), 1000)  # Reasonable limit
    limit = min(int(request.args.get('limit', 50)), 1000)  # Reasonable limit
    offset = (page - 1) * limit
    
    conn = get_db_connection()
    if not conn:
        return jsonify({'error': 'Database connection failed'}), 500
    
    try:
        cursor = conn.cursor(cursor_factory=psycopg2.extras.RealDictCursor)
        
        # Check if table exists
        cursor.execute("""
            SELECT count(*) as exists FROM information_schema.tables 
            WHERE table_name = %s AND table_schema = 'public'
        """, (table_name,))
        if cursor.fetchone()['exists'] == 0:
            cursor.close()
            release_db_connection(conn)
            return jsonify({'error': 'Table not found'}), 404
        
        # Get total count
        cursor.execute(f'SELECT count(*) as total FROM "{table_name}"')
        total = cursor.fetchone()['total']
        
        # Get data safely
        cursor.execute(f'SELECT * FROM "{table_name}" LIMIT %s OFFSET %s', (limit, offset))
        data = cursor.fetchall()
        
        cursor.close()
        release_db_connection(conn)
        
        return jsonify({
            'table_name': table_name,
            'data': [dict(row) for row in data],
            'pagination': {
                'page': page,
                'limit': limit,
                'total': total,
                'pages': (total + limit - 1) // limit
            }
        })
        
    except Exception as e:
        release_db_connection(conn)
        logger.error(f"Table data error: {e}")
        return jsonify({'error': str(e)}), 500

if __name__ == '__main__':
    logger.info("Starting Secure PostgreSQL Database API...")
    logger.info(f"Database: {DB_CONFIG['database']}@{DB_CONFIG['host']}:{DB_CONFIG['port']}")
    app.run(host='0.0.0.0', port=5000, debug=False)
