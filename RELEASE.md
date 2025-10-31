# ShipSync Release Guide

This guide explains how to create releases for the ShipSync WordPress plugin.

## Release Script

The `release.sh` script automates the release process.

### Usage

```bash
./release.sh [command]
```

### Commands

#### Create a Release (Default)
```bash
./release.sh
# or
./release.sh release
```

This will:
1. Check you're on the main branch (with warning if not)
2. Verify there are no uncommitted changes
3. Optionally update the version number
4. Commit changes
5. Create a git tag
6. Push commits and tags to GitHub

#### Check Current Version
```bash
./release.sh version
```

#### Show Help
```bash
./release.sh help
```

### Manual Release Process

If you prefer to do it manually:

1. **Update Version Numbers**
   ```bash
   # Update shipsync.php
   # Update readme.txt (Stable tag)
   ```

2. **Commit Changes**
   ```bash
   git add .
   git commit -m "Release version X.Y.Z"
   ```

3. **Create Tag**
   ```bash
   git tag -a vX.Y.Z -m "Release version X.Y.Z"
   ```

4. **Push to GitHub**
   ```bash
   git push origin main
   git push origin vX.Y.Z
   ```

5. **Create GitHub Release**
   - Go to: https://github.com/dreygur/shipsync/releases/new
   - Select the tag you just created
   - Add release notes from CHANGELOG.md
   - Optionally upload a plugin zip file

### Version Numbering

Follow [Semantic Versioning](https://semver.org/):
- **MAJOR** (X.0.0): Breaking changes
- **MINOR** (0.X.0): New features (backward compatible)
- **PATCH** (0.0.X): Bug fixes

### Files That Need Version Updates

When releasing, update version in:
1. `shipsync.php` - Plugin header
2. `readme.txt` - Stable tag line

The release script can do this automatically.

## Example Release

```bash
# Start release process
./release.sh

# Script will ask:
# - Update version? (y/N)
#   If yes: Enter new version (e.g., 2.1.0)
# - Ready to create release? (y/N)

# After release, create GitHub release with notes
```

## Troubleshooting

### "You have uncommitted changes"
Commit or stash your changes first:
```bash
git stash  # or git commit -am "Your message"
```

### "Could not determine version"
Make sure `shipsync.php` has a `Version:` header line.

### Tag already exists
If you need to overwrite a tag:
```bash
git tag -d vX.Y.Z           # Delete local tag
git push origin :refs/tags/vX.Y.Z  # Delete remote tag
# Then run release script again
```

## Git Workflow

For feature development:
1. Create feature branch: `git checkout -b feature/feature-name`
2. Make changes and commit
3. Push branch: `git push origin feature/feature-name`
4. Create Pull Request on GitHub
5. Merge to main
6. Create release from main branch

