#!/bin/bash

# Configuration
VERSION="1.0.0"
PLUGIN_NAME="joby-sync"

echo "🚀 Starting release process for $PLUGIN_NAME v$VERSION..."

# 1. Create a clean ZIP for WordPress
echo "📦 Packaging plugin..."
zip -r "${PLUGIN_NAME}.zip" . -x "*.git*" ".DS_Store" "*.sh" "README.md" "LICENSE" "CODE_OF_CONDUCT.md"
# Note: Usually README, LICENSE, and COC are included in the zip for WP. 
# Re-running zip to include them but exclude Git.
zip -r "${PLUGIN_NAME}.zip" . -x "*.git*" ".DS_Store" "*.sh"

# 2. Git Workflow
echo "Git staging..."
git add .

echo "💾 Committing changes..."
git commit -m "chore: prepare v$VERSION release"

echo "📤 Pushing to GitHub..."
git push origin main

echo "✅ Release $VERSION prepared and pushed!"
echo "🔗 You can now find ${PLUGIN_NAME}.zip in your folder to upload to GitHub/WordPress."
