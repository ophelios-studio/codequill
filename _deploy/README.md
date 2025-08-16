# Deployment Configuration

This directory contains all the deployment-related files for the Zephyrus Fly application.

## Initial Fly.io Setup

### 1. Create Apps

First, you need to create the Fly apps in the Ophelios organization using the respective configuration files:

```bash
fly launch --config _deploy/fly_dev.toml --dockerfile _deploy/Dockerfile --org ophelios
```

```bash
fly launch --config _deploy/fly_prod.toml --dockerfile _deploy/Dockerfile --org ophelios
```

### 2. Create and Attach Database

Create a PostgreSQL database and attach it to your apps:

```bash
fly postgres create
```

```bash
fly postgres attach codequill-dev-db --app codequill-dev
```

```bash
fly postgres attach codequill-db --app codequill
```

### 3. Configure Secrets

Set the required environment variables as Fly secrets based on your `.env` file via `fly secrets` or using the Fly.io web interface :

```bash
fly secrets set \
  DEBUG=1 \
  CACHE_UPDATE=always \
  DB_HOSTNAME=your_dev_db_host \
  DB_USERNAME=your_dev_db_user \
  DB_PASSWORD=your_dev_db_password \
  DB_NAME=your_dev_db_name \
  PASSWORD_PEPPER=your_password_pepper \
  ENCRYPTION_KEY=your_encryption_key \
  --app zephyrus-fly-test-dev
```

```bash
fly secrets set \
  DEBUG=0 \
  CACHE_UPDATE=always \
  DB_HOSTNAME=your_prod_db_host \
  DB_USERNAME=your_prod_db_user \
  DB_PASSWORD=your_prod_db_password \
  DB_NAME=your_prod_db_name \
  PASSWORD_PEPPER=your_password_pepper \
  ENCRYPTION_KEY=your_encryption_key \
  --app zephyrus-fly-test
```

### 4. Deploy

Once an app is created, you can re-deploy the applications via `fly deploy` :

```bash
fly deploy --config _deploy/fly_dev.toml --dockerfile _deploy/Dockerfile
```

```bash
fly deploy --config _deploy/fly_prod.toml --dockerfile _deploy/Dockerfile
```

## App Targeting

You can also target apps in several ways :

### **By app name** (recommended for specific commands):
```bash
fly deploy --app codequill-dev
fly deploy --app codequill
```

### **By config file** (what we use in our workflows):
```bash
fly deploy --config _deploy/fly_dev.toml --dockerfile _deploy/Dockerfile
fly deploy --config _deploy/fly_prod.toml --dockerfile _deploy/Dockerfile
```

### **By config file + app override**:
```bash
fly deploy --config _deploy/fly_dev.toml --dockerfile _deploy/Dockerfile --app codequill-dev
```

## Connect to apps

```bash
fly ssh console --app codequill
```

```bash
fly ssh console --app codequill-db
```

## Create token for deploy (if not automatically created)

```bash
fly tokens create org -o ophelios
```