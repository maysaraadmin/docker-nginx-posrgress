#!/bin/bash

# Moodle Kubernetes Deployment Script
# This script deploys Moodle LMS on Kubernetes with all components

set -e

echo "Deploying Moodle LMS on Kubernetes..."

# Check if kubectl is available
if ! command -v kubectl &> /dev/null; then
    echo "Error: kubectl is not installed or not in PATH"
    exit 1
fi

# Create namespace
echo "Creating namespace..."
kubectl apply -f namespace.yaml

# Apply secrets and config
echo "Applying secrets and configuration..."
kubectl apply -f secrets.yaml
kubectl apply -f configmap.yaml

# Apply persistent volume claims
echo "Creating persistent volume claims..."
kubectl apply -f pvc/

# Wait for PVCs to be bound
echo "Waiting for PVCs to be bound..."
kubectl wait --for=condition=Bound pvc/postgres-pvc -n moodle --timeout=300s
kubectl wait --for=condition=Bound pvc/moodledata-pvc -n moodle --timeout=300s

# Deploy PostgreSQL
echo "Deploying PostgreSQL..."
kubectl apply -f postgres/

# Wait for PostgreSQL to be ready
echo "Waiting for PostgreSQL to be ready..."
kubectl wait --for=condition=Ready pod -l app=postgres -n moodle --timeout=300s

# Deploy Moodle
echo "Deploying Moodle application..."
kubectl apply -f moodle/

# Wait for Moodle to be ready
echo "Waiting for Moodle to be ready..."
kubectl wait --for=condition=Ready pod -l app=moodle -n moodle --timeout=600s

# Deploy cron job
echo "Deploying Moodle cron job..."
kubectl apply -f cronjob.yaml

# Apply ingress (optional - comment out if not using ingress)
echo "Applying ingress configuration..."
kubectl apply -f ingress.yaml

# Display deployment status
echo "Deployment completed! Checking status..."
kubectl get pods -n moodle
kubectl get services -n moodle
kubectl get pvc -n moodle

# Show access information
echo ""
echo "Access Information:"
echo "=================="
echo "Moodle Service: kubectl port-forward service/moodle 8080:80 -n moodle"
echo "Then access: http://localhost:8080"
echo ""
echo "Database Access: kubectl exec -it -n moodle deployment/postgres -- psql -U moodle_admin -d moodle"
echo ""
echo "Moodle Logs: kubectl logs -n moodle deployment/moodle -f"
echo "PostgreSQL Logs: kubectl logs -n moodle deployment/postgres -f"
echo ""
echo "Cron Jobs: kubectl get cronjobs -n moodle"
echo ""
echo "To cleanup: kubectl delete namespace moodle"
