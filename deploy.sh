#!/bin/bash
# deploy.sh - Production deployment script
# Usage: ./deploy.sh [environment]

set -e

ENVIRONMENT=${1:-production}
PROJECT_DIR="/var/www/html/online-shop"
BACKUP_DIR="/var/backups/online-shop"

echo "🚀 Starting deployment to $ENVIRONMENT..."

# Create backup
echo "📦 Creating backup..."
mkdir -p $BACKUP_DIR
BACKUP_NAME="backup-$(date +%Y%m%d-%H%M%S)"
tar -czf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" -C $PROJECT_DIR .

# Pull latest code
echo "📥 Pulling latest code..."
cd $PROJECT_DIR
git fetch origin
git reset --hard origin/main

# Install/update dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --prefer-dist --no-progress --optimize-autoloader --no-interaction

# Frontend build
if [ -f "package.json" ]; then
    echo "🎨 Building frontend assets..."
    npm ci --production
    npm run build
fi

# Laravel optimization
echo "⚡ Optimizing Laravel..."
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
php artisan filament:cache 2>/dev/null || true

# Clear old caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Re-cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Set permissions
echo "🔐 Setting permissions..."
chown -R www-data:www-data $PROJECT_DIR/storage $PROJECT_DIR/bootstrap/cache
chmod -R 775 $PROJECT_DIR/storage $PROJECT_DIR/bootstrap/cache

# Restart services
echo "🔄 Restarting services..."
supervisorctl restart all

# Health check
echo "🏥 Running health check..."
sleep 5
if curl -f http://localhost/health > /dev/null 2>&1; then
    echo "✅ Deployment successful!"
else
    echo "❌ Health check failed! Rolling back..."
    tar -xzf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" -C $PROJECT_DIR
    supervisorctl restart all
    exit 1
fi

# Cleanup old backups (keep last 5)
echo "🧹 Cleaning up old backups..."
ls -t $BACKUP_DIR/backup-*.tar.gz | tail -n +6 | xargs -r rm

echo "✨ Deployment complete!"