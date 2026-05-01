#!/usr/bin/env bash
set -e

REPO_URL="https://github.com/lanbinleo/qiwi"
SCRIPT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"

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
echo "Latest commit: $(git log -1 --pretty=format:'%h - %s')"
