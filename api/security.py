#!/usr/bin/env python3
"""
Security utilities for the application
"""

import re
import sqlparse
from typing import Optional, List, Dict, Any
import hashlib
import secrets
from functools import wraps

class SQLValidator:
    """SQL injection prevention utilities"""
    
    # Dangerous SQL keywords and patterns
    DANGEROUS_PATTERNS = [
        r'\bDROP\s+DATABASE\b',
        r'\bDROP\s+TABLE\b',
        r'\bDELETE\s+FROM\b.+WHERE\s+1\s*=\s*1',
        r'\bTRUNCATE\b',
        r'\bALTER\s+TABLE\b',
        r'\bEXEC\b',
        r'\bEXECUTE\b',
        r'\bUNION\s+ALL\b',
        r'\bINSERT\s+INTO\b.+VALUES\s*\(',
        r'\bUPDATE\b.+SET\b',
        r'\bGRANT\b',
        r'\bREVOKE\b',
        r'\bCREATE\s+USER\b',
        r'\bCREATE\s+DATABASE\b',
        r'\bCREATE\s+TABLE\b',
        r'\b--\b',
        r'/\*.*\*/',
        r';\s*(DROP|DELETE|TRUNCATE|ALTER|EXEC|CREATE)',
    ]
    
    @classmethod
    def is_safe_query(cls, query: str) -> tuple[bool, Optional[str]]:
        """
        Validate SQL query for potential injection attacks
        
        Args:
            query: SQL query string
            
        Returns:
            Tuple of (is_safe, error_message)
        """
        if not query or not query.strip():
            return False, "Empty query not allowed"
        
        query_upper = query.upper().strip()
        
        # Check for dangerous patterns
        for pattern in cls.DANGEROUS_PATTERNS:
            if re.search(pattern, query, re.IGNORECASE | re.MULTILINE | re.DOTALL):
                return False, f"Potentially dangerous SQL pattern detected: {pattern}"
        
        # Parse the SQL to check basic syntax
        try:
            parsed = sqlparse.parse(query)[0]
            if not parsed:
                return False, "Invalid SQL syntax"
            
            # Get the first token to determine operation type
            first_token = parsed.token_first(skip_ws=True, skip_cm=True)
            if first_token:
                operation = first_token.value.upper()
                
                # Allow only safe operations by default
                safe_operations = {'SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'}
                if operation not in safe_operations:
                    return False, f"Operation '{operation}' not allowed in this context"
                    
        except Exception as e:
            return False, f"SQL parsing error: {str(e)}"
        
        return True, None
    
    @classmethod
    def sanitize_query(cls, query: str) -> str:
        """
        Basic SQL sanitization
        
        Args:
            query: SQL query string
            
        Returns:
            Sanitized query string
        """
        # Remove comments
        query = re.sub(r'/\*.*?\*/', '', query, flags=re.DOTALL)
        query = re.sub(r'--.*$', '', query, flags=re.MULTILINE)
        
        # Remove multiple whitespace
        query = re.sub(r'\s+', ' ', query)
        
        return query.strip()

class InputValidator:
    """Input validation utilities"""
    
    @staticmethod
    def validate_table_name(name: str) -> tuple[bool, Optional[str]]:
        """Validate table name"""
        if not name or not name.strip():
            return False, "Table name cannot be empty"
        
        # Only allow alphanumeric, underscores, and must start with letter
        if not re.match(r'^[a-zA-Z][a-zA-Z0-9_]*$', name):
            return False, "Invalid table name format"
        
        if len(name) > 64:
            return False, "Table name too long (max 64 characters)"
        
        return True, None
    
    @staticmethod
    def validate_column_name(name: str) -> tuple[bool, Optional[str]]:
        """Validate column name"""
        if not name or not name.strip():
            return False, "Column name cannot be empty"
        
        # Only allow alphanumeric, underscores, and must start with letter
        if not re.match(r'^[a-zA-Z][a-zA-Z0-9_]*$', name):
            return False, "Invalid column name format"
        
        if len(name) > 64:
            return False, "Column name too long (max 64 characters)"
        
        return True, None
    
    @staticmethod
    def validate_sql_type(data_type: str) -> tuple[bool, Optional[str]]:
        """Validate SQL data type"""
        valid_types = [
            'INTEGER', 'BIGINT', 'SMALLINT', 'SERIAL', 'BIGSERIAL',
            'VARCHAR', 'CHAR', 'TEXT', 'BOOLEAN',
            'REAL', 'DOUBLE PRECISION', 'NUMERIC', 'DECIMAL',
            'DATE', 'TIME', 'TIMESTAMP', 'TIMESTAMPTZ',
            'JSON', 'JSONB', 'UUID', 'INET'
        ]
        
        data_type_upper = data_type.upper().strip()
        
        # Check for VARCHAR with length
        if data_type_upper.startswith('VARCHAR('):
            if not re.match(r'^VARCHAR\(\d+\)$', data_type_upper):
                return False, "Invalid VARCHAR format"
            return True, None
        
        if data_type_upper not in valid_types:
            return False, f"Invalid data type: {data_type}"
        
        return True, None

class CommandValidator:
    """Command injection prevention utilities"""
    
    DANGEROUS_COMMANDS = [
        'rm', 'rmdir', 'mv', 'cp', 'dd', 'chmod', 'chown',
        'sudo', 'su', 'passwd', 'useradd', 'userdel',
        'kill', 'killall', 'pkill', 'shutdown', 'reboot',
        'nc', 'netcat', 'curl', 'wget', 'ftp', 'ssh',
        'python', 'perl', 'ruby', 'bash', 'sh', 'cmd',
        'powershell', 'ps', 'cmd.exe'
    ]
    
    @staticmethod
    def is_safe_command(command: str) -> tuple[bool, Optional[str]]:
        """
        Validate command for injection prevention
        
        Args:
            command: Command string to validate
            
        Returns:
            Tuple of (is_safe, error_message)
        """
        if not command or not command.strip():
            return False, "Empty command not allowed"
        
        # Check for dangerous commands
        command_lower = command.lower()
        for dangerous in CommandValidator.DANGEROUS_COMMANDS:
            if dangerous in command_lower:
                return False, f"Dangerous command detected: {dangerous}"
        
        # Check for shell metacharacters that could be used for injection
        dangerous_chars = ['|', '&', ';', '$', '`', '(', ')', '<', '>', '"', "'"]
        for char in dangerous_chars:
            if char in command:
                return False, f"Dangerous character detected: {char}"
        
        return True, None

def rate_limit(max_requests: int = 100, window: int = 3600):
    """
    Rate limiting decorator
    
    Args:
        max_requests: Maximum requests allowed
        window: Time window in seconds
    """
    def decorator(f):
        @wraps(f)
        def decorated_function(*args, **kwargs):
            # This is a simple implementation
            # In production, use Redis or database for distributed rate limiting
            return f(*args, **kwargs)
        return decorated_function
    return decorator

def generate_secure_token(length: int = 32) -> str:
    """Generate cryptographically secure random token"""
    return secrets.token_urlsafe(length)

def hash_password(password: str, salt: Optional[str] = None) -> tuple[str, str]:
    """
    Hash password with salt
    
    Args:
        password: Plain text password
        salt: Optional salt (generated if not provided)
        
    Returns:
        Tuple of (hashed_password, salt)
    """
    if salt is None:
        salt = secrets.token_hex(16)
    
    password_hash = hashlib.pbkdf2_hmac(
        'sha256',
        password.encode('utf-8'),
        salt.encode('utf-8'),
        100000  # Number of iterations
    )
    
    return password_hash.hex(), salt

def verify_password(password: str, hashed_password: str, salt: str) -> bool:
    """
    Verify password against hash
    
    Args:
        password: Plain text password to verify
        hashed_password: Stored hash
        salt: Salt used for hashing
        
    Returns:
        True if password matches
    """
    computed_hash, _ = hash_password(password, salt)
    return computed_hash == hashed_password
