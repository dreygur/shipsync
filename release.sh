#!/bin/bash

# ShipSync Release Script
# This script helps create and tag releases for the ShipSync WordPress plugin

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Get version from plugin file
VERSION=$(grep "Version:" shipsync.php | sed 's/.*Version: *\([0-9.]*\).*/\1/')

# Get current branch
CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD)

echo -e "${BLUE}🚀 ShipSync Release Script${NC}"
echo -e "${BLUE}=========================${NC}\n"

# Function to check if we're on main branch
check_branch() {
    if [ "$CURRENT_BRANCH" != "main" ]; then
        echo -e "${YELLOW}⚠️  Warning: You're on branch '$CURRENT_BRANCH', not 'main'${NC}"
        read -p "Continue anyway? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
}

# Function to check for uncommitted changes
check_changes() {
    if [ -n "$(git status --porcelain)" ]; then
        echo -e "${RED}❌ Error: You have uncommitted changes${NC}"
        echo "Please commit or stash your changes before releasing."
        git status --short
        exit 1
    fi
}

# Function to check version
check_version() {
    if [ -z "$VERSION" ]; then
        echo -e "${RED}❌ Error: Could not determine version from shipsync.php${NC}"
        exit 1
    fi
    echo -e "${GREEN}✓ Current version: ${VERSION}${NC}\n"
}

# Function to update version
update_version() {
    read -p "Enter new version (current: $VERSION): " NEW_VERSION
    if [ -z "$NEW_VERSION" ]; then
        echo -e "${YELLOW}No version entered, keeping current version${NC}"
        return
    fi

    # Update version in main plugin file
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s/Version: ${VERSION}/Version: ${NEW_VERSION}/" shipsync.php
    else
        # Linux
        sed -i "s/Version: ${VERSION}/Version: ${NEW_VERSION}/" shipsync.php
    fi

    # Update version in readme.txt
    if [[ "$OSTYPE" == "darwin"* ]]; then
        sed -i '' "s/Stable tag: ${VERSION}/Stable tag: ${NEW_VERSION}/" readme.txt
    else
        sed -i "s/Stable tag: ${VERSION}/Stable tag: ${NEW_VERSION}/" readme.txt
    fi

    VERSION=$NEW_VERSION
    echo -e "${GREEN}✓ Version updated to ${VERSION}${NC}\n"
}

# Function to create release
create_release() {
    check_branch
    check_changes
    check_version

    echo -e "${BLUE}Creating release for version ${VERSION}...${NC}\n"

    # Ask if user wants to update version
    read -p "Update version number? (y/N): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        update_version
    fi

    # Show what will be committed
    echo -e "${BLUE}Files to be committed:${NC}"
    git status --short

    # Ask for confirmation
    echo -e "\n${YELLOW}Ready to create release?${NC}"
    read -p "This will commit changes, create a tag, and push to remote. Continue? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo -e "${YELLOW}Release cancelled${NC}"
        exit 0
    fi

    # Stage all changes
    echo -e "\n${BLUE}Staging changes...${NC}"
    git add -A

    # Commit
    echo -e "${BLUE}Committing changes...${NC}"
    git commit -m "Release version ${VERSION}

- Updated version to ${VERSION}
- See CHANGELOG.md for details" || {
        echo -e "${RED}❌ Commit failed. Make sure you have changes to commit.${NC}"
        exit 1
    }

    # Create tag
    TAG="v${VERSION}"
    echo -e "\n${BLUE}Creating tag ${TAG}...${NC}"
    git tag -a "$TAG" -m "Release version ${VERSION}"

    # Push commits
    echo -e "\n${BLUE}Pushing commits to origin...${NC}"
    git push origin "$CURRENT_BRANCH"

    # Push tags
    echo -e "${BLUE}Pushing tags to origin...${NC}"
    git push origin "$TAG"

    echo -e "\n${GREEN}✅ Release ${VERSION} created successfully!${NC}"
    echo -e "${GREEN}Tag: ${TAG}${NC}\n"

    echo -e "${BLUE}Next steps:${NC}"
    echo -e "1. Create a release on GitHub: https://github.com/dreygur/shipsync/releases/new"
    echo -e "2. Select tag: ${TAG}"
    echo -e "3. Add release notes from CHANGELOG.md"
    echo -e "4. Upload plugin zip file (if needed)\n"
}

# Function to show help
show_help() {
    echo "ShipSync Release Script"
    echo ""
    echo "Usage: ./release.sh [command]"
    echo ""
    echo "Commands:"
    echo "  release     Create a new release (default)"
    echo "  version     Show current version"
    echo "  help        Show this help message"
    echo ""
}

# Main script logic
case "${1:-release}" in
    release)
        create_release
        ;;
    version)
        check_version
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo -e "${RED}Unknown command: $1${NC}"
        show_help
        exit 1
        ;;
esac

