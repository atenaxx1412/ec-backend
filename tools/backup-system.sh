#!/bin/bash

# Database Backup System for EC Site
# Supports both development and production environments
# Usage: ./backup-system.sh [full|incremental|restore] [backup_file]

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
BACKUP_DIR="$PROJECT_ROOT/backups"
LOG_FILE="$BACKUP_DIR/backup.log"
RETENTION_DAYS=30

# Load environment variables
if [ -f "$PROJECT_ROOT/.env" ]; then
    source "$PROJECT_ROOT/.env"
fi

# Default values
DB_HOST=${DB_HOST:-mysql}
DB_DATABASE=${DB_DATABASE:-ecommerce_dev_db}
DB_USERNAME=${DB_USERNAME:-ec_dev_user}
DB_PASSWORD=${DB_PASSWORD:-dev_password_123}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD:-root_password_dev}

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    local level=$1
    shift
    local message="$*"
    local timestamp=$(date '+%Y-%m-%d %H:%M:%S')
    
    echo -e "${timestamp} [${level}] ${message}" | tee -a "$LOG_FILE"
    
    case $level in
        "ERROR")   echo -e "${RED}ERROR: ${message}${NC}" ;;
        "SUCCESS") echo -e "${GREEN}SUCCESS: ${message}${NC}" ;;
        "WARNING") echo -e "${YELLOW}WARNING: ${message}${NC}" ;;
        "INFO")    echo -e "${BLUE}INFO: ${message}${NC}" ;;
    esac
}

# Initialize backup directory
init_backup_dir() {
    if [ ! -d "$BACKUP_DIR" ]; then
        mkdir -p "$BACKUP_DIR"
        log "INFO" "Created backup directory: $BACKUP_DIR"
    fi
    
    if [ ! -f "$LOG_FILE" ]; then
        touch "$LOG_FILE"
        log "INFO" "Created backup log file: $LOG_FILE"
    fi
}

# Check if running in Docker environment
is_docker_env() {
    if command -v docker-compose &> /dev/null; then
        if docker-compose ps mysql | grep -q "Up"; then
            return 0
        fi
    fi
    return 1
}

# Execute MySQL command
mysql_exec() {
    local command="$1"
    local use_root=${2:-false}
    
    if is_docker_env; then
        if [ "$use_root" = true ]; then
            docker-compose exec mysql mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "$command"
        else
            docker-compose exec mysql mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$command"
        fi
    else
        # Direct MySQL connection (production environment)
        if [ "$use_root" = true ]; then
            mysql -h "$DB_HOST" -u root -p"$MYSQL_ROOT_PASSWORD" -e "$command"
        else
            mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "$command"
        fi
    fi
}

# Execute mysqldump
mysqldump_exec() {
    local options="$1"
    local output_file="$2"
    
    if is_docker_env; then
        docker-compose exec mysql mysqldump -u "$DB_USERNAME" -p"$DB_PASSWORD" $options "$DB_DATABASE" > "$output_file"
    else
        mysqldump -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" $options "$DB_DATABASE" > "$output_file"
    fi
}

# Full backup
backup_full() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_file="$BACKUP_DIR/full_backup_${timestamp}.sql"
    local compressed_file="$backup_file.gz"
    
    log "INFO" "Starting full backup..."
    
    # Create database structure and data backup
    log "INFO" "Creating full database dump..."
    mysqldump_exec "--single-transaction --routines --triggers --events --add-drop-table --complete-insert" "$backup_file"
    
    if [ $? -eq 0 ]; then
        log "SUCCESS" "Database dump created: $backup_file"
        
        # Compress the backup
        log "INFO" "Compressing backup..."
        gzip "$backup_file"
        
        if [ $? -eq 0 ]; then
            log "SUCCESS" "Backup compressed: $compressed_file"
            
            # Create backup metadata
            local metadata_file="$BACKUP_DIR/full_backup_${timestamp}.meta"
            cat > "$metadata_file" << EOF
{
    "type": "full",
    "timestamp": "$timestamp",
    "database": "$DB_DATABASE",
    "file": "$(basename "$compressed_file")",
    "size": "$(du -h "$compressed_file" | cut -f1)",
    "created_at": "$(date -Iseconds)",
    "tables": [
$(mysql_exec "SELECT CONCAT('        \"', TABLE_NAME, '\"') FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$DB_DATABASE' ORDER BY TABLE_NAME;" | grep -v CONCAT | sed '$!s/$/,/')
    ]
}
EOF
            log "SUCCESS" "Backup metadata created: $metadata_file"
            
            return 0
        else
            log "ERROR" "Failed to compress backup"
            return 1
        fi
    else
        log "ERROR" "Failed to create database dump"
        return 1
    fi
}

# Incremental backup (binary logs)
backup_incremental() {
    local timestamp=$(date '+%Y%m%d_%H%M%S')
    local backup_file="$BACKUP_DIR/incremental_backup_${timestamp}.sql"
    
    log "INFO" "Starting incremental backup..."
    
    # Check if binary logging is enabled
    local binlog_status=$(mysql_exec "SHOW VARIABLES LIKE 'log_bin';" true | grep log_bin | awk '{print $2}')
    
    if [ "$binlog_status" != "ON" ]; then
        log "WARNING" "Binary logging is not enabled. Creating differential backup instead..."
        
        # Get the last full backup timestamp
        local last_backup=$(ls -t "$BACKUP_DIR"/full_backup_*.meta 2>/dev/null | head -1)
        if [ -z "$last_backup" ]; then
            log "ERROR" "No full backup found. Please create a full backup first."
            return 1
        fi
        
        local last_timestamp=$(basename "$last_backup" .meta | sed 's/full_backup_//')
        log "INFO" "Creating differential backup since: $last_timestamp"
        
        # Create backup with only changed data (using created_at/updated_at timestamps)
        mysqldump_exec "--single-transaction --where=\"created_at >= '$last_timestamp' OR updated_at >= '$last_timestamp'\"" "$backup_file"
    else
        log "INFO" "Binary logging is enabled. Flushing logs..."
        mysql_exec "FLUSH LOGS;" true
        
        # In a real production environment, you would copy the binary logs
        # For this demo, we'll create a metadata file indicating the log position
        local log_position=$(mysql_exec "SHOW MASTER STATUS;" true | tail -1)
        echo "Binary log position: $log_position" > "$backup_file"
    fi
    
    if [ $? -eq 0 ]; then
        log "SUCCESS" "Incremental backup created: $backup_file"
        
        # Create metadata
        local metadata_file="$BACKUP_DIR/incremental_backup_${timestamp}.meta"
        cat > "$metadata_file" << EOF
{
    "type": "incremental",
    "timestamp": "$timestamp",
    "database": "$DB_DATABASE",
    "file": "$(basename "$backup_file")",
    "size": "$(du -h "$backup_file" | cut -f1)",
    "created_at": "$(date -Iseconds)"
}
EOF
        log "SUCCESS" "Incremental backup completed"
        return 0
    else
        log "ERROR" "Failed to create incremental backup"
        return 1
    fi
}

# Restore from backup
restore_backup() {
    local backup_file="$1"
    
    if [ -z "$backup_file" ]; then
        log "ERROR" "Backup file not specified"
        return 1
    fi
    
    if [ ! -f "$backup_file" ]; then
        log "ERROR" "Backup file not found: $backup_file"
        return 1
    fi
    
    log "WARNING" "This will restore the database and overwrite existing data."
    read -p "Are you sure you want to continue? (y/N): " -n 1 -r
    echo
    
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        log "INFO" "Restore cancelled by user"
        return 0
    fi
    
    log "INFO" "Starting database restore from: $backup_file"
    
    # Create temporary restore file
    local temp_file="/tmp/restore_$(basename "$backup_file")"
    
    # Decompress if needed
    if [[ "$backup_file" == *.gz ]]; then
        log "INFO" "Decompressing backup file..."
        gunzip -c "$backup_file" > "$temp_file"
    else
        cp "$backup_file" "$temp_file"
    fi
    
    # Restore database
    if is_docker_env; then
        log "INFO" "Restoring database in Docker environment..."
        docker-compose exec -T mysql mysql -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$temp_file"
    else
        log "INFO" "Restoring database..."
        mysql -h "$DB_HOST" -u "$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" < "$temp_file"
    fi
    
    if [ $? -eq 0 ]; then
        log "SUCCESS" "Database restored successfully"
        
        # Verify restore
        local table_count=$(mysql_exec "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = '$DB_DATABASE';" | tail -1)
        log "INFO" "Verified: $table_count tables restored"
        
        # Clean up
        rm -f "$temp_file"
        return 0
    else
        log "ERROR" "Failed to restore database"
        rm -f "$temp_file"
        return 1
    fi
}

# List available backups
list_backups() {
    log "INFO" "Available backups:"
    echo
    
    if [ ! -d "$BACKUP_DIR" ] || [ -z "$(ls -A "$BACKUP_DIR" 2>/dev/null)" ]; then
        log "WARNING" "No backups found in $BACKUP_DIR"
        return 0
    fi
    
    printf "%-20s %-15s %-10s %-15s %s\n" "TYPE" "TIMESTAMP" "SIZE" "CREATED" "FILE"
    printf "%-20s %-15s %-10s %-15s %s\n" "----" "---------" "----" "-------" "----"
    
    for meta_file in "$BACKUP_DIR"/*.meta; do
        if [ -f "$meta_file" ]; then
            local type=$(grep '"type"' "$meta_file" | cut -d'"' -f4)
            local timestamp=$(grep '"timestamp"' "$meta_file" | cut -d'"' -f4)
            local size=$(grep '"size"' "$meta_file" | cut -d'"' -f4)
            local created=$(grep '"created_at"' "$meta_file" | cut -d'"' -f4 | cut -d'T' -f1)
            local file=$(grep '"file"' "$meta_file" | cut -d'"' -f4)
            
            printf "%-20s %-15s %-10s %-15s %s\n" "$type" "$timestamp" "$size" "$created" "$file"
        fi
    done
}

# Clean old backups
cleanup_old_backups() {
    log "INFO" "Cleaning up backups older than $RETENTION_DAYS days..."
    
    local deleted_count=0
    
    # Find and delete old backup files
    find "$BACKUP_DIR" -name "*.sql.gz" -type f -mtime +$RETENTION_DAYS -print0 | while IFS= read -r -d '' file; do
        log "INFO" "Deleting old backup: $(basename "$file")"
        rm -f "$file"
        
        # Also delete corresponding metadata file
        local meta_file="${file%.sql.gz}.meta"
        if [ -f "$meta_file" ]; then
            rm -f "$meta_file"
        fi
        
        ((deleted_count++))
    done
    
    if [ $deleted_count -gt 0 ]; then
        log "SUCCESS" "Cleaned up $deleted_count old backup files"
    else
        log "INFO" "No old backups to clean up"
    fi
}

# Database health check
health_check() {
    log "INFO" "Performing database health check..."
    
    # Check connection
    if mysql_exec "SELECT 1;" >/dev/null 2>&1; then
        log "SUCCESS" "Database connection: OK"
    else
        log "ERROR" "Database connection: FAILED"
        return 1
    fi
    
    # Check table integrity
    local tables=$(mysql_exec "SHOW TABLES;" | grep -v Tables_in)
    local check_failed=0
    
    while IFS= read -r table; do
        if [ -n "$table" ]; then
            local check_result=$(mysql_exec "CHECK TABLE $table;" | tail -1 | awk '{print $4}')
            if [ "$check_result" = "OK" ]; then
                log "SUCCESS" "Table $table: OK"
            else
                log "ERROR" "Table $table: $check_result"
                ((check_failed++))
            fi
        fi
    done <<< "$tables"
    
    if [ $check_failed -eq 0 ]; then
        log "SUCCESS" "All tables passed integrity check"
    else
        log "WARNING" "$check_failed tables failed integrity check"
    fi
    
    # Show database statistics
    log "INFO" "Database statistics:"
    mysql_exec "
        SELECT 
            TABLE_NAME,
            TABLE_ROWS,
            ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as 'Size_MB'
        FROM information_schema.TABLES 
        WHERE TABLE_SCHEMA = '$DB_DATABASE'
            AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC;
    "
}

# Show usage
show_usage() {
    echo "Usage: $0 [COMMAND] [OPTIONS]"
    echo
    echo "Commands:"
    echo "  full                    Create full database backup"
    echo "  incremental             Create incremental backup"
    echo "  restore <backup_file>   Restore from backup file"
    echo "  list                    List available backups"
    echo "  cleanup                 Remove old backup files"
    echo "  health                  Perform database health check"
    echo "  help                    Show this help message"
    echo
    echo "Examples:"
    echo "  $0 full"
    echo "  $0 incremental"
    echo "  $0 restore backups/full_backup_20231201_120000.sql.gz"
    echo "  $0 list"
    echo "  $0 health"
    echo
}

# Main execution
main() {
    init_backup_dir
    
    case "${1:-help}" in
        "full")
            backup_full
            ;;
        "incremental")
            backup_incremental
            ;;
        "restore")
            restore_backup "$2"
            ;;
        "list")
            list_backups
            ;;
        "cleanup")
            cleanup_old_backups
            ;;
        "health")
            health_check
            ;;
        "help"|"--help"|"-h")
            show_usage
            ;;
        *)
            log "ERROR" "Unknown command: $1"
            show_usage
            exit 1
            ;;
    esac
}

# Execute main function
main "$@"