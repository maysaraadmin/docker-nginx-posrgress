# 🚀 Start Moodle on Kubernetes - Complete Guide

## 📋 Prerequisites Check

### ✅ Current Status
- **Docker Desktop**: ✅ Installed (v4.70.0)
- **kubectl**: ✅ Installed (v1.34.1)
- **K8s Config**: ✅ Available in moodle-k8s/
- **❌ Missing**: Kubernetes cluster

## 🔧 Step 1: Enable Kubernetes in Docker Desktop

### Option A: Docker Desktop GUI (Recommended)
1. **Open Docker Desktop**
2. **Go to Settings** → **Kubernetes**
3. **Enable Kubernetes**:
   - ✅ Check "Enable Kubernetes"
   - ✅ Check "Kubernetes"
   - ✅ Check "Default system CRI"
4. **Apply & Restart** (takes 2-3 minutes)

### Option B: Command Line
```powershell
# Enable Kubernetes via Docker Desktop CLI
docker context list
docker context use default
```

## 🔧 Step 2: Verify Kubernetes Cluster

```powershell
# Check cluster status
kubectl cluster-info

# Check nodes
kubectl get nodes

# Check all namespaces
kubectl get namespaces
```

## 🚀 Step 3: Deploy Moodle on Kubernetes

### Method A: Automated Deployment (Recommended)
```powershell
# Navigate to K8s directory
cd moodle-k8s

# Make deployment script executable (PowerShell)
# Windows: Just run the script
./deploy.sh
```

### Method B: Manual Step-by-Step Deployment
```powershell
# 1. Create namespace
kubectl apply -f namespace.yaml

# 2. Apply configuration
kubectl apply -f configmap.yaml
kubectl apply -f secrets.yaml

# 3. Create storage
kubectl apply -f pvc/

# 4. Deploy database
kubectl apply -f postgres/

# 5. Deploy Moodle
kubectl apply -f moodle/

# 6. Deploy cron job
kubectl apply -f cronjob.yaml

# 7. Configure ingress
kubectl apply -f ingress.yaml
```

## 🔍 Step 4: Monitor Deployment

```powershell
# Check all pods
kubectl get pods -n moodle

# Check services
kubectl get services -n moodle

# Check persistent volumes
kubectl get pvc -n moodle

# Watch deployment progress
kubectl get pods -n moodle -w
```

## 🌐 Step 5: Access Moodle

### Option A: Port Forward (Development)
```powershell
# Forward port to localhost
kubectl port-forward service/moodle 8080:80 -n moodle

# Access in browser
# http://localhost:8080
```

### Option B: Ingress (Production)
```powershell
# Check ingress status
kubectl get ingress -n moodle

# Access via configured domain
# http://moodle.localdomain (if configured)
# or https://your-domain.com
```

## 🔧 Step 6: Verify Installation

### Check Moodle Status
```powershell
# Moodle logs
kubectl logs -n moodle deployment/moodle -f

# PostgreSQL logs
kubectl logs -n moodle deployment/postgres -f

# Check database connection
kubectl exec -it -n moodle deployment/postgres -- psql -U moodle_admin -d moodle
```

### Access Moodle Admin
- **URL**: http://localhost:8080 (with port-forward)
- **Username**: admin
- **Password**: Check secrets.yaml or set during installation

## 📊 Expected Results

### Successful Deployment
```
NAMESPACE   NAME                          READY   STATUS    RESTARTS   AGE
moodle      moodle-xxxxx-yyyyy            1/1     Running   0          5m
moodle      postgres-xxxxx-yyyyy          1/1     Running   0          7m
moodle      moodle-cron-xxxxx-yyyyy       0/1     Completed 0          2m

NAMESPACE   NAME         TYPE        CLUSTER-IP      EXTERNAL-IP   PORT(S)   AGE
moodle      moodle       ClusterIP   10.108.123.45   <none>        80/TCP    5m
moodle      postgres     ClusterIP   10.108.234.56   <none>        5432/TCP  7m

NAMESPACE   NAME                          STATUS   VOLUME                                     CAPACITY   ACCESS MODES
moodle      moodledata-pvc               Bound    pvc-xxxxx-yyyyy-zzzzz                      50Gi       RWO
moodle      postgres-pvc                 Bound    pvc-xxxxx-yyyyy-aaaaa                      20Gi       RWO
```

## 🛠️ Troubleshooting

### Common Issues & Solutions

#### 1. Kubernetes Not Starting
```powershell
# Check Docker Desktop Kubernetes status
# Restart Docker Desktop
# Verify system resources (min 4GB RAM)
```

#### 2. Pods Not Starting
```powershell
# Describe pod for details
kubectl describe pod <pod-name> -n moodle

# Check events
kubectl get events -n moodle --sort-by='.lastTimestamp'

# Check resource limits
kubectl top nodes
kubectl top pods -n moodle
```

#### 3. PVC Not Binding
```powershell
# Check storage classes
kubectl get storageclass

# Check PVC status
kubectl get pvc -n moodle -o wide

# Default storage class should be available
```

#### 4. Database Connection Issues
```powershell
# Check database secrets
kubectl get secrets -n moodle

# Test database connection
kubectl exec -it -n moodle deployment/postgres -- psql -U moodle_admin -d moodle

# Check Moodle environment variables
kubectl exec -it -n moodle deployment/moodle -- env | grep MOODLE
```

#### 5. Ingress Not Working
```powershell
# Check ingress controller
kubectl get pods -n ingress-nginx

# Check ingress configuration
kubectl describe ingress moodle-ingress -n moodle

# Test service connectivity
kubectl exec -it -n moodle deployment/moodle -- curl http://moodle
```

## 🔄 Step 7: Management Commands

### Scale Moodle
```powershell
# Scale to 3 replicas (requires ReadWriteMany storage)
kubectl scale deployment moodle --replicas=3 -n moodle
```

### Update Moodle
```powershell
# Update deployment
kubectl apply -f moodle/

# Rolling restart
kubectl rollout restart deployment/moodle -n moodle
```

### Backup Database
```powershell
# Create database backup
kubectl exec -n moodle deployment/postgres -- pg_dump -U moodle_admin moodle > moodle-backup.sql
```

### Cleanup
```powershell
# Delete entire deployment
kubectl delete namespace moodle

# Or delete specific resources
kubectl delete -f .
```

## 📱 Mobile Access

### Phone Access
```powershell
# Get your IP
ipconfig

# Access from phone
# http://[your-ip]:8080 (with port-forward)
```

## 🎯 Success Indicators

### ✅ Working Setup
- **Kubernetes cluster**: Running in Docker Desktop
- **All pods**: Running (1/1 or 0/1 for cron)
- **Services**: ClusterIP created
- **Storage**: PVCs bound
- **Moodle**: Accessible via browser
- **Database**: Connected and working

### 🎉 Final Result
- **Beautiful Moodle UI**: Full Bootstrap theme
- **Admin access**: Complete control panel
- **Course management**: Ready for content
- **User management**: Multi-user support
- **Scalable architecture**: Ready for production

## 📞 Support

If you encounter issues:
1. Check Docker Desktop Kubernetes settings
2. Verify system resources (RAM, CPU)
3. Review pod logs and events
4. Check storage class availability
5. Ensure all prerequisites are met

**Your Moodle LMS will be running on Kubernetes with enterprise-grade scalability!** 🚀
