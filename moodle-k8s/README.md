# Moodle LMS on Kubernetes

Production-ready Moodle Learning Management System deployment on Kubernetes with PostgreSQL, persistent storage, and automated cron jobs.

## Architecture

```
moodle-k8s/
moodle/                    # Moodle application
  deployment.yaml         # Moodle pod deployment
  service.yaml           # Moodle service
postgres/                 # PostgreSQL database
  deployment.yaml         # PostgreSQL + service
pvc/                      # Persistent storage
  moodledata-pvc.yaml     # Moodle data storage (50Gi)
  postgres-pvc.yaml       # PostgreSQL storage (20Gi)
configmap.yaml           # Configuration (non-secrets)
secrets.yaml             # Database & admin passwords
ingress.yaml             # HTTPS ingress with cert-manager
cronjob.yaml             # Moodle cron (every minute)
namespace.yaml           # Dedicated namespace
deploy.sh                # Deployment script
```

## Features

- **High Availability**: Health checks and resource limits
- **Persistent Storage**: Separate PVCs for data and database
- **Security**: Secrets management and network policies
- **Automation**: Cron jobs for Moodle tasks
- **Monitoring**: Health probes and readiness checks
- **Scalability**: Ready for horizontal scaling
- **HTTPS**: TLS termination with cert-manager

## Quick Start

### Prerequisites

- Kubernetes cluster (v1.20+)
- kubectl configured
- Storage class available (default: `standard`)
- Ingress controller (nginx recommended)
- cert-manager (for HTTPS)

### Deployment

1. **Clone and configure:**
```bash
git clone <repository-url>
cd docker-nginx-posrgress/moodle-k8s
```

2. **Customize configuration:**
```bash
# Update secrets.yaml with your passwords
# Update ingress.yaml with your domain
# Update storage class in PVCs if needed
```

3. **Deploy all components:**
```bash
# Using the deployment script (recommended)
chmod +x deploy.sh
./deploy.sh

# Or manually
kubectl apply -f .
```

### Access Moodle

**Option 1: Port Forward (Development)**
```bash
kubectl port-forward service/moodle 8080:80 -n moodle
# Access: http://localhost:8080
```

**Option 2: Ingress (Production)**
```bash
# Update moodle.localdomain in ingress.yaml to your domain
kubectl apply -f ingress.yaml
# Access: https://your-domain.com
```

## Configuration

### Database Settings
- **Database**: PostgreSQL 15
- **User**: moodle_admin
- **Password**: Set in secrets.yaml
- **Storage**: 20Gi persistent volume

### Moodle Settings
- **Version**: moodlehq/moodle-php-apache:8.3-20250331
- **Auto-install**: Enabled
- **Admin User**: admin (password from secrets.yaml)
- **Data Storage**: 50Gi persistent volume

### Resource Limits
- **Moodle**: 512Mi-1Gi memory, 500m-1000m CPU
- **PostgreSQL**: 256Mi-512Mi memory, 250m-500m CPU
- **Cron**: 128Mi-256Mi memory, 100m-200m CPU

## Management

### Check Status
```bash
# Pods
kubectl get pods -n moodle

# Services
kubectl get services -n moodle

# Persistent Volumes
kubectl get pvc -n moodle

# Cron Jobs
kubectl get cronjobs -n moodle
```

### Logs
```bash
# Moodle logs
kubectl logs -n moodle deployment/moodle -f

# PostgreSQL logs
kubectl logs -n moodle deployment/postgres -f

# Cron job logs
kubectl logs -n moodle job/moodle-cron-<timestamp>
```

### Database Access
```bash
# Connect to database
kubectl exec -it -n moodle deployment/postgres -- psql -U moodle_admin -d moodle

# Backup database
kubectl exec -n moodle deployment/postgres -- pg_dump -U moodle_admin moodle > backup.sql
```

### Scale Moodle
```bash
# Scale to 3 replicas (requires RWX storage)
kubectl scale deployment moodle --replicas=3 -n moodle
```

## Security

### Secrets Management
- Database password stored in Kubernetes secrets
- Admin password stored in Kubernetes secrets
- Moodle secret key for enhanced security

### Network Security
- Dedicated namespace isolation
- Internal service communication
- Ingress with TLS termination

### Access Control
- Pod security policies (if enabled)
- RBAC for cluster access
- Network policies (if configured)

## Troubleshooting

### Common Issues

**Pod not starting:**
```bash
kubectl describe pod <pod-name> -n moodle
kubectl logs <pod-name> -n moodle
```

**PVC not binding:**
```bash
kubectl get pvc -n moodle
kubectl get storageclass
```

**Database connection issues:**
```bash
# Check database user and password
kubectl exec -n moodle deployment/postgres -- psql -U moodle_admin -d moodle -c "\du"

# Check database connectivity from Moodle pod
kubectl exec -n moodle deployment/moodle -- php -r "new PDO('pgsql:host=postgres;dbname=moodle', 'moodle_admin', 'password');"
```

**Ingress not working:**
```bash
kubectl get ingress -n moodle
kubectl describe ingress moodle-ingress -n moodle
```

### Cleanup
```bash
# Delete entire deployment
kubectl delete namespace moodle

# Delete specific components
kubectl delete -f .
```

## Production Considerations

### Storage
- Use cloud provider storage classes
- Configure backup strategies
- Monitor storage usage
- Consider storage performance (SSD vs HDD)

### Scaling
- Use ReadWriteMany storage for horizontal scaling
- Configure external database for better performance
- Consider load balancing for high traffic
- Implement session affinity

### Monitoring
- Deploy Prometheus and Grafana
- Monitor resource usage
- Set up alerting for pod failures
- Track application performance

### Backup Strategy
- Regular database backups
- File system backups for moodledata
- Test restore procedures
- Store backups off-cluster

## Customization

### Custom Moodle Image
```yaml
# Update moodle/deployment.yaml
image: your-custom-moodle:tag
```

### Custom Resources
```yaml
# Update resource limits in deployment.yaml
resources:
  requests:
    memory: "1Gi"
    cpu: "1000m"
  limits:
    memory: "2Gi"
    cpu: "2000m"
```

### Custom Storage Classes
```yaml
# Update storageClassName in PVCs
storageClassName: fast-ssd  # your custom storage class
```

## Support

For issues and questions:
1. Check the troubleshooting section
2. Review Kubernetes events: `kubectl get events -n moodle`
3. Check pod logs and describe output
4. Verify cluster resources and storage classes

## License

This project is licensed under the MIT License - see the main project LICENSE file for details.
