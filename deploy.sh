#!/bin/bash
# Exit immediately if a command exits with a non-zero status.
set -e
umask 0002

# --- Configuration ---
# The name of the Git branch you want to deploy.
BRANCH="main"
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
WEB_USER="${WEB_USER:-www-data}"
SHARED_GROUP="${SHARED_GROUP:-www-data}"
WRITABLE_PATHS=(
  "$APP_DIR/storage"
  "$APP_DIR/web/cpresources"
)

run_as_web_user() {
  if [ "$(id -un)" = "$WEB_USER" ]; then
    "$@"
    return
  fi

  if command -v sudo >/dev/null 2>&1 && sudo -n -u "$WEB_USER" true >/dev/null 2>&1; then
    sudo -n -u "$WEB_USER" "$@"
    return
  fi

  return 1
}

run_craft_command() {
  if run_as_web_user php craft "$@"; then
    return
  fi

  echo "Warning: could not switch to '$WEB_USER'; running Craft as '$(id -un)'."
  php craft "$@"
}

apply_craft_permissions() {
  echo "Ensuring Craft writable paths..."
  for path in "${WRITABLE_PATHS[@]}"; do
    mkdir -p "$path"
    if [ -d "$path" ]; then
      find "$path" -type d -exec chmod g+rws {} + 2>/dev/null || true
      find "$path" -type f -exec chmod g+rw {} + 2>/dev/null || true
    fi
  done

  if getent group "$SHARED_GROUP" >/dev/null 2>&1; then
    for path in "${WRITABLE_PATHS[@]}"; do
      chgrp -R "$SHARED_GROUP" "$path" 2>/dev/null || true
    done
  fi
}

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
apply_craft_permissions
run_craft_command up --interactive=0

# Clear Craft's caches to ensure all changes take effect immediately.
echo "Clearing Craft caches..."
apply_craft_permissions
run_craft_command clear-caches/all

echo "--- Deployment finished successfully ---"
