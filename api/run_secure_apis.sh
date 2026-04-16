#!/bin/bash

# Start both secure database and nginx APIs
echo "Starting Secure Database API on port 5000..."
python database_secure.py &

echo "Starting Secure Nginx API on port 5001..."
python nginx_secure.py &

# Wait for both processes
wait
