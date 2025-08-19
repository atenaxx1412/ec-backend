#!/bin/bash

# Automated Backup Cron Script for EC Site
# This script is designed to be run by cron for automated backups
# Add to crontab with: crontab -e
# 0 * * * * /path/to/ec-backend/tools/backup-cron.sh >> /var/log/backup-cron.log 2>&1

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_SCRIPT="$SCRIPT_DIR/backup-system.sh"

# Day of week (0=Sunday, 1=Monday, etc.)
DAY_OF_WEEK=$(date +%w)

# Hour of day
HOUR=$(date +%H)

# Backup strategy:
# - Full backup: Every Sunday at 2 AM
# - Incremental backup: Every day except Sunday at 2 AM
# - Cleanup: Every Sunday at 3 AM

case $DAY_OF_WEEK in
    0) # Sunday
        if [ "$HOUR" = "02" ]; then
            echo "$(date): Running weekly full backup"
            "$BACKUP_SCRIPT" full
        elif [ "$HOUR" = "03" ]; then
            echo "$(date): Running backup cleanup"
            "$BACKUP_SCRIPT" cleanup
        fi
        ;;
    *) # Monday-Saturday
        if [ "$HOUR" = "02" ]; then
            echo "$(date): Running daily incremental backup"
            "$BACKUP_SCRIPT" incremental
        fi
        ;;
esac

# Run health check every day at 1 AM
if [ "$HOUR" = "01" ]; then
    echo "$(date): Running daily health check"
    "$BACKUP_SCRIPT" health
fi