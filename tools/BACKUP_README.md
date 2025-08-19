# Database Backup System

This directory contains a comprehensive database backup solution for the EC site.

## Files

- `backup-system.sh` - Main backup script with full functionality
- `backup-cron.sh` - Automated backup scheduler for cron
- `BACKUP_README.md` - This documentation file

## Features

### Backup Types
- **Full Backup**: Complete database dump with structure and data
- **Incremental Backup**: Changes since last backup (differential mode when binary logs unavailable)
- **Compressed Storage**: Automatic gzip compression to save space
- **Metadata Tracking**: JSON metadata files for each backup

### Operations
- **Health Check**: Database connectivity and table integrity verification
- **Restore**: Database restoration from backup files
- **Cleanup**: Automatic removal of old backups (30-day retention)
- **List**: Display all available backups with details

## Usage

### Manual Backup Operations

```bash
# Create full backup
./tools/backup-system.sh full

# Create incremental backup
./tools/backup-system.sh incremental

# List all backups
./tools/backup-system.sh list

# Restore from backup
./tools/backup-system.sh restore backups/full_backup_20231201_120000.sql.gz

# Database health check
./tools/backup-system.sh health

# Clean old backups
./tools/backup-system.sh cleanup

# Show help
./tools/backup-system.sh help
```

### Automated Backup Schedule

Add to crontab for automated backups:

```bash
# Edit crontab
crontab -e

# Add this line for hourly backup checks
0 * * * * /path/to/ec-backend/tools/backup-cron.sh >> /var/log/backup-cron.log 2>&1
```

### Backup Schedule
- **Sunday 2 AM**: Full backup
- **Monday-Saturday 2 AM**: Incremental backup
- **Sunday 3 AM**: Cleanup old backups
- **Daily 1 AM**: Health check

## Configuration

The backup system reads configuration from `.env` file:

```bash
# Database Configuration
DB_HOST=mysql
DB_DATABASE=ecommerce_dev_db
DB_USERNAME=ec_dev_user
DB_PASSWORD=dev_password_123
MYSQL_ROOT_PASSWORD=root_password_dev
```

## Directory Structure

```
backups/
├── full_backup_20231201_120000.sql.gz     # Compressed backup file
├── full_backup_20231201_120000.meta       # Backup metadata (JSON)
├── incremental_backup_20231202_020000.sql # Incremental backup
├── incremental_backup_20231202_020000.meta # Incremental metadata
└── backup.log                             # Operation log file
```

## Backup Metadata Example

```json
{
    "type": "full",
    "timestamp": "20231201_120000",
    "database": "ecommerce_dev_db",
    "file": "full_backup_20231201_120000.sql.gz",
    "size": "16K",
    "created_at": "2023-12-01T12:00:00+09:00",
    "tables": [
        "products",
        "categories",
        "users",
        "orders",
        "order_items",
        "cart",
        "product_reviews",
        "wishlist",
        "coupons",
        "admins",
        "user_addresses",
        "product_attributes",
        "product_categories",
        "inventory_movements",
        "product_views",
        "analytics_daily",
        "active_products",
        "featured_products",
        "products_with_reviews"
    ]
}
```

## Environment Support

The backup system automatically detects the environment:

- **Docker Environment**: Uses `docker-compose exec` commands
- **Direct MySQL**: Connects directly to MySQL server

## Security Considerations

### Development Environment
- Passwords are stored in `.env` file
- Backup files are stored locally
- Suitable for development and testing

### Production Environment
- Use environment variables for sensitive data
- Store backups on separate secure storage
- Implement encryption for backup files
- Use network-attached storage or cloud storage
- Set up monitoring and alerting

## Production Deployment Recommendations

1. **Remote Storage**: Configure cloud storage (AWS S3, Google Cloud Storage)
2. **Encryption**: Add GPG encryption for backup files
3. **Monitoring**: Set up backup success/failure notifications
4. **Testing**: Regular restore testing to verify backup integrity
5. **Documentation**: Maintain runbooks for disaster recovery

## Troubleshooting

### Common Issues

1. **Permission Denied**
   ```bash
   chmod +x tools/backup-system.sh
   chmod +x tools/backup-cron.sh
   ```

2. **Database Connection Failed**
   - Check `.env` configuration
   - Verify database is running: `docker-compose ps mysql`
   - Check credentials

3. **Backup Files Not Found**
   - Ensure `backups/` directory exists
   - Check file permissions
   - Verify disk space

4. **Restore Failed**
   - Verify backup file integrity: `gunzip -t backup_file.gz`
   - Check database permissions
   - Ensure target database exists

### Log Files

Check log files for detailed error information:
- `backups/backup.log` - Backup operation log
- `/var/log/backup-cron.log` - Cron execution log

## Recovery Procedures

### Complete Database Recovery

1. **Stop Application**
   ```bash
   docker-compose stop api
   ```

2. **Restore Database**
   ```bash
   ./tools/backup-system.sh restore backups/latest_full_backup.sql.gz
   ```

3. **Apply Incremental Backups** (if needed)
   ```bash
   ./tools/backup-system.sh restore backups/incremental_backup_YYYYMMDD_HHMMSS.sql
   ```

4. **Verify Restore**
   ```bash
   ./tools/backup-system.sh health
   ```

5. **Restart Application**
   ```bash
   docker-compose start api
   ```

### Point-in-Time Recovery

For point-in-time recovery, use the closest full backup and apply incremental backups up to the desired time point.

## Monitoring and Alerts

Consider implementing:
- Backup completion notifications
- Backup failure alerts
- Storage space monitoring
- Backup file integrity checks
- Recovery time testing

This backup system provides a solid foundation for database protection in both development and production environments.