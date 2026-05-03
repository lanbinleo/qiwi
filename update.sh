#!/usr/bin/env bash
set -e

REPO_URL="https://github.com/lanbinleo/qiwi"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
TYPECHO_USR_DIR="$(cd -- "$SCRIPT_DIR/../.." && pwd)"
THEME_PLUGIN_DIR="$SCRIPT_DIR/plugins"
TYPECHO_PLUGIN_DIR="$TYPECHO_USR_DIR/plugins"

BLUE='\033[0;34m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

info() {
    echo -e "${BLUE}INFO${NC} $1"
}

success() {
    echo -e "${GREEN}OK${NC} $1"
}

warning() {
    echo -e "${YELLOW}WARN${NC} $1"
}

error() {
    echo -e "${RED}ERROR${NC} $1"
}

cd "$SCRIPT_DIR"

echo "=== Qiwi theme updater ==="
echo "Theme path: $SCRIPT_DIR"
echo "Plugin path: $TYPECHO_PLUGIN_DIR"
echo "Repository: $REPO_URL"
echo

if ! command -v git >/dev/null 2>&1; then
    error "git is not installed."
    exit 1
fi

if [ ! -d ".git" ]; then
    error "Current theme folder is not a git repository."
    echo "Clone it first:"
    echo "  cd \"$(dirname "$SCRIPT_DIR")\" && git clone $REPO_URL qiwi"
    exit 1
fi

sync_companion_plugins() {
    if [ ! -d "$THEME_PLUGIN_DIR" ]; then
        info "No companion plugin directory found; skipping plugin sync."
        return
    fi

    mkdir -p "$TYPECHO_PLUGIN_DIR"

    shopt -s nullglob
    local plugin_source
    local plugin_name
    local plugin_target
    local synced_count=0

    for plugin_source in "$THEME_PLUGIN_DIR"/*; do
        if [ ! -d "$plugin_source" ]; then
            continue
        fi

        plugin_name="$(basename "$plugin_source")"
        plugin_target="$TYPECHO_PLUGIN_DIR/$plugin_name"

        if [ -z "$plugin_name" ] || [ "$plugin_name" = "." ] || [ "$plugin_name" = ".." ]; then
            error "Refusing to sync invalid plugin name: $plugin_name"
            exit 1
        fi

        if [ "$plugin_target" = "/" ] || [ "$plugin_target" = "$TYPECHO_PLUGIN_DIR" ]; then
            error "Refusing to replace unsafe plugin target: $plugin_target"
            exit 1
        fi

        info "Syncing companion plugin: $plugin_name"
        rm -rf "$plugin_target"
        cp -R "$plugin_source" "$plugin_target"
        synced_count=$((synced_count + 1))
    done
    shopt -u nullglob

    if [ "$synced_count" -eq 0 ]; then
        info "No companion plugins to sync."
    else
        success "Synced $synced_count companion plugin(s)."
    fi
}

current_remote="$(git remote get-url origin 2>/dev/null || true)"
if [ -n "$current_remote" ] && [ "$current_remote" != "$REPO_URL" ]; then
    warning "origin is $current_remote"
fi

branch="$(git branch --show-current 2>/dev/null || true)"
if [ -z "$branch" ]; then
    branch="main"
fi

info "Current branch: $branch"

if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
    warning "Local changes detected. git pull may fail if files conflict."
    git status --short
    echo
fi

info "Fetching latest changes..."
git fetch --prune origin

upstream="$(git rev-parse --abbrev-ref --symbolic-full-name '@{u}' 2>/dev/null || true)"
if [ -n "$upstream" ]; then
    info "Pulling from $upstream..."
    git pull --ff-only --progress
else
    info "No upstream configured; pulling origin/$branch..."
    git pull --ff-only --progress origin "$branch"
fi

echo
success "Qiwi theme updated."
sync_companion_plugins
echo "Latest commit: $(git log -1 --pretty=format:'%h - %s')"
