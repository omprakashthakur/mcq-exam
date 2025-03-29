#!/bin/bash

# Set deployment variables
DEPLOY_PATH="/opt/bitnami/apache2/htdocs/mcq-exam"
BACKUP_PATH="$DEPLOY_PATH/backups"
UPLOAD_PATH="$DEPLOY_PATH/uploads"
LOG_PATH="$DEPLOY_PATH/logs"

# Create necessary directories
mkdir -p "$BACKUP_PATH"
mkdir -p "$UPLOAD_PATH"
mkdir -p "$LOG_PATH"

# Set proper permissions
chown -R bitnami:daemon "$DEPLOY_PATH"
chmod -R 750 "$DEPLOY_PATH"
chmod -R 770 "$BACKUP_PATH"
chmod -R 770 "$UPLOAD_PATH"
chmod -R 770 "$LOG_PATH"

# Copy environment file if not exists
if [ ! -f "$DEPLOY_PATH/.env" ]; then
    cp "$DEPLOY_PATH/.env.example" "$DEPLOY_PATH/.env"
fi

# Create symbolic link for uploads if needed
if [ ! -L "/opt/bitnami/apache2/htdocs/uploads" ]; then
    ln -s "$UPLOAD_PATH" "/opt/bitnami/apache2/htdocs/uploads"
fi

# Secure installation file
if [ -f "$DEPLOY_PATH/install.php" ]; then
    mv "$DEPLOY_PATH/install.php" "$DEPLOY_PATH/install.php.bak"
fi

echo "Initial setup completed successfully!"