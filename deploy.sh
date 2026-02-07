#!/bin/bash
# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
# The name of the Git branch you want to deploy.
BRANCH="main"

echo "--- Starting Git & Craft CMS deployment ---"

# Delete composer.lock and vendor directory BEFORE pulling from Git
# This prevents conflicts if these files exist in the repository
echo "Deleting composer.lock file and vendor directory..."
rm -rf composer.lock
rm -rf vendor

# Pull the latest code from the configured 'origin' remote on the specified branch.
echo "Pulling latest code from Git branch '$BRANCH'..."
git pull origin "$BRANCH"

# Install or update Composer dependencies.
# The "--no-dev" flag ensures only production dependencies are installed.
echo "Installing Composer dependencies..."
composer install --no-interaction --no-progress --no-dev

# Apply pending Craft and plugin database migrations, and project config changes.
echo "Applying database migrations and project config..."
php craft up --interactive=0

# Clear Craft's caches to ensure all changes take effect immediately.
echo "Clearing Craft caches..."
php craft flush-caches/all

echo "--- Deployment finished successfully ---"
