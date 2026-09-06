#!/bin/bash

# ==============================================================================
#  GIT AUTO-DEPLOY SCRIPT (CRON JOB)
# ==============================================================================
# Purpose: This script is intended to be run by a Cron Job to automatically
# keep your project updated with the latest code from GitHub.
#
# Usage:
# Add this to your Cron Job (every 1-5 minutes):
# /bin/bash /home/kaishopi/domains/tmail.kaishop.id.vn/public_html/git_deploy.sh
# ==============================================================================

# Auto-detect project directory from script location, fallback to default hosting path
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [ -f "${SCRIPT_DIR}/scripts/build.php" ]; then
    PROJECT_DIR="${SCRIPT_DIR}"
else
    PROJECT_DIR="/home/kaishopi/domains/tmail.kaishop.id.vn/public_html"
fi

LOG_FILE="${PROJECT_DIR}/storage/logs/cron_deploy.log"
BRANCH="main"

# Ensure log directory exists
mkdir -p "$(dirname "$LOG_FILE")"

run_deploy() {
    echo "------------------------------------------------------------"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting Git Pull & Deploy..."
    
    cd "$PROJECT_DIR" || { echo "ERROR: Could not change directory to $PROJECT_DIR"; exit 1; }
    
    # Discard any local build artifact modifications so git pull never gets blocked
    git checkout -- static/ 2>/dev/null
    
    # Run git pull
    # We use --ff-only to ensure we don't accidentally create merge commits on server
    git pull origin "$BRANCH" --ff-only 2>&1
    EXIT_CODE=$?
    
    if [ $EXIT_CODE -eq 0 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Success: Code updated from branch $BRANCH."
        
        # If static assets were already compiled and committed from dev (Terser mangled), keep them.
        # Otherwise, fallback to pure PHP compiler if manifest is missing.
        if [ ! -f "$PROJECT_DIR/static/manifest.json" ] && [ -f "$PROJECT_DIR/scripts/build.php" ]; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] Assets manifest missing. Compiling via Pure PHP fallback..."
            php "$PROJECT_DIR/scripts/build.php" 2>&1
        fi
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Deployment completed successfully."
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] Error: Git pull failed with exit code $EXIT_CODE."
    fi
}

# If running interactively in a terminal, print to screen AND append to log file.
# If running via automated cron job, write to log file.
if [ -t 1 ]; then
    run_deploy 2>&1 | tee -a "$LOG_FILE"
else
    run_deploy >> "$LOG_FILE" 2>&1
fi
