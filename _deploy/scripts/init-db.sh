#!/bin/sh
set -e

# Only run database initialization for production environments
if [ "$APP_ENV" = "dev" ]; then
  echo "🔍 Checking if database has already been initialized..."

  # Check if database has already been initialized by looking for dedicated marker table
  if PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOSTNAME" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_NAME" -c "SELECT 1 FROM information_schema.tables WHERE table_name = 'fly_deployment_initialized';" 2>/dev/null | grep -q "1"; then
    echo "✅ Database already initialized, skipping initialization."
    exit 0
  fi
fi

echo "🔐 Disconnecting all users and dropping/recreating $DB_NAME on $DB_HOSTNAME..."

# Disconnect everyone from the target DB
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOSTNAME" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres -c "
  SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = '$DB_NAME' AND pid <> pg_backend_pid();
"

echo "⏳ Starting database initialization..."

# TODO: Figure something else for paths
#cd /var/www/html

# Delete entire database if already exists by connecting to default database
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOSTNAME" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres -c "DROP DATABASE IF EXISTS $DB_NAME;"
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOSTNAME" -p "$DB_PORT" -U "$DB_USERNAME" -d postgres -c "CREATE DATABASE $DB_NAME;"

echo "📥 Applying init-database.sql..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOSTNAME" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_NAME" < ./sql/init-database.sql

echo "📥 Applying init-mocks.sql..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOSTNAME" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_NAME" < ./sql/init-mocks.sql

# Create initialization marker table
echo "🏷️ Creating initialization marker..."
PGPASSWORD="$DB_PASSWORD" psql -h "$DB_HOSTNAME" -p "$DB_PORT" -U "$DB_USERNAME" -d "$DB_NAME" -c "
CREATE TABLE IF NOT EXISTS fly_deployment_initialized (
  id SERIAL PRIMARY KEY,
  initialized_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  deployment_id VARCHAR(255),
  environment VARCHAR(50) DEFAULT 'prod'
);"

echo "✅ Database initialization complete."
