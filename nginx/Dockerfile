FROM nginx:1.24-alpine

# Copy custom nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf
COPY conf.d/ /etc/nginx/conf.d/

# Create directories for SSL certificates
RUN mkdir -p /etc/letsencrypt/live/localhost

# Expose ports for HTTP and HTTPS
EXPOSE 80 443

# Start nginx
CMD ["nginx", "-g", "daemon off;"]
