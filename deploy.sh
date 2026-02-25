#!/bin/bash
# Exit immediately if a command exits with a non-zero status.
set -e

# --- Configuration ---
# The name of the Git branch you want to deploy.
BRANCH="main"
APP_DIR="$(cd "$(dirname "$0")" && pwd)"
WEB_USER="${WEB_USER:-www-data}"
DEPLOY_USER="${DEPLOY_USER:-$(id -un)}"
SHARED_GROUP="${SHARED_GROUP:-craft}"
WRITABLE_PATHS=(
  "$APP_DIR/storage"
  "$APP_DIR/web/cpresources"
)

run_craft_command() {
  if [ "$(id -un)" = "$WEB_USER" ]; then
    php craft "$@"
    return
  fi

  if command -v sudo >/dev/null 2>&1 && sudo -n -u "$WEB_USER" true >/dev/null 2>&1; then
    sudo -n -u "$WEB_USER" php craft "$@"
    return
  fi

  echo "Warning: could not switch to '$WEB_USER'; running Craft as '$(id -un)'."
  php craft "$@"
}

apply_craft_permissions() {
  echo "Applying Craft writable permissions..."

  if getent group "$SHARED_GROUP" >/dev/null 2>&1; then
    for path in "${WRITABLE_PATHS[@]}"; do
      if [ -d "$path" ]; then
        chgrp -R "$SHARED_GROUP" "$path" || true
        find "$path" -type d -exec chmod 2775 {} \;
        find "$path" -type f -exec chmod 664 {} \;
      fi
    done
  else
    echo "Shared group '$SHARED_GROUP' not found, skipping chgrp/chmod group setup."
  fi

  if command -v setfacl >/dev/null 2>&1; then
    for path in "${WRITABLE_PATHS[@]}"; do
      if [ -d "$path" ]; then
        setfacl -R -m "u:$DEPLOY_USER:rwx,u:$WEB_USER:rwx,g:$SHARED_GROUP:rwx" "$path" || true
        setfacl -dR -m "u:$DEPLOY_USER:rwx,u:$WEB_USER:rwx,g:$SHARED_GROUP:rwx" "$path" || true
      fi
    done
  else
    echo "setfacl not found, skipping ACL configuration."
  fi
}

clear_compiled_templates_with_retry() {
  local compiled_dir="$APP_DIR/storage/runtime/compiled_templates"
  local attempt

  for attempt in 1 2 3; do
    if [ ! -d "$compiled_dir" ]; then
      return
    fi

    if ! find "$compiled_dir" -mindepth 1 -print -quit | grep -q .; then
      return
    fi

    echo "Retrying compiled templates clear (attempt $attempt/3)..."
    run_craft_command clear-caches/compiled-templates || true
    find "$compiled_dir" -mindepth 1 -exec rm -rf {} + || true
    apply_craft_permissions
    sleep 1
  done
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
clear_compiled_templates_with_retry

echo "--- Deployment finished successfully ---"
