# Cron Job Setup Instructions

## Purpose
The auto-confirm-cron.php script automatically confirms ticket purchases and releases payment to sellers after the 3-day confirmation period expires.

## Setup Instructions

### 1. Make the cron script executable
```bash
chmod +x backend/auto-confirm-cron.php
```

### 2. Add to system crontab
Run this command to edit the system crontab:
```bash
sudo crontab -e
```

### 3. Add the following line to run daily at 2 AM
```
0 2 * * * /usr/bin/php /home/keschler/Documents/Ticket-Share/backend/auto-confirm-cron.php >> /var/log/ticket-share-cron.log 2>&1
```

### 4. Alternative: Run as specific user
If you prefer to run as a specific user (recommended):
```bash
crontab -e
```
Then add:
```
0 2 * * * /usr/bin/php /home/keschler/Documents/Ticket-Share/backend/auto-confirm-cron.php >> ~/ticket-share-cron.log 2>&1
```

### 5. Verify cron is running
Check if the cron service is active:
```bash
sudo systemctl status cron
```

### 6. Manual testing
You can manually test the script:
```bash
php backend/auto-confirm-cron.php
```

## Log Monitoring
- Check `/var/log/ticket-share-cron.log` (system cron) or `~/ticket-share-cron.log` (user cron) for execution logs
- The script will log how many confirmations were auto-processed

## Frequency Options
- Daily at 2 AM: `0 2 * * *`
- Every 6 hours: `0 */6 * * *`
- Every hour: `0 * * * *`

## Important Notes
- Ensure the web server has write permissions to the database
- The script should run with the same user permissions as your web server for database access
- Monitor the logs regularly to ensure the cron job is working correctly
