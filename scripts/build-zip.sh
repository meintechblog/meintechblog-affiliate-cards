#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BUILD_DIR="$ROOT_DIR/build"
ZIP_PATH="$BUILD_DIR/meintechblog-affiliate-cards.zip"
STAGE_DIR="$BUILD_DIR/meintechblog-affiliate-cards"

rm -rf "$STAGE_DIR"
mkdir -p "$STAGE_DIR"
mkdir -p "$BUILD_DIR"

rsync -a \
  --exclude '.git' \
  --exclude 'build' \
  --exclude '.DS_Store' \
  "$ROOT_DIR/" "$STAGE_DIR/"

cd "$BUILD_DIR"
rm -f "$ZIP_PATH"
zip -qr "$ZIP_PATH" meintechblog-affiliate-cards

printf 'Built %s\n' "$ZIP_PATH"
