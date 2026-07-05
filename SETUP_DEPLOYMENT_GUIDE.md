# 🚀 Complete Setup & Deployment Guide

## Table of Contents
1. [Prerequisites](#prerequisites)
2. [Local Development Setup](#local-development-setup)
3. [Building for Production](#building-for-production)
4. [Deployment to Server](#deployment-to-server)
5. [Environment Configuration](#environment-configuration)
6. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Software
- **Node.js** >= 18.x (download from [nodejs.org](https://nodejs.org))
- **npm** >= 9.x (comes with Node.js)
- **Git** (for version control)
- **PHP** >= 8.0 (for backend, on your server)
- **MySQL** >= 5.7 (database on your server)

### Verify Installation
```bash
node --version    # Should show v18.x or higher
npm --version     # Should show 9.x or higher
php --version     # Should show 8.x or higher (on server)
```

---

## Local Development Setup

### Step 1: Clone/Navigate to Project
```bash
cd c:\Users\ihebc\OneDrive\Desktop\code_source
```

### Step 2: Install Dependencies
```bash
npm install
```

This will:
- Download all packages from `package.json`
- Create `node_modules/` folder
- Generate `package-lock.json`

**Expected time:** 2-5 minutes depending on internet speed

### Step 3: Configure Environment Variables

The `.env` file is already configured for testing:
```env
VITE_API_BASE_URL=https://crm.ttshop.pro/code_source/backend/php
```

For **local development**, you can override it:
```env
VITE_API_BASE_URL=http://localhost:8000/backend/php
```

### Step 4: Start Development Server
```bash
npm run dev
```

**Output:**
```
  VITE v7.3.1  ready in 523 ms

  ➜  Local:   http://localhost:5173/
  ➜  press h + enter to show help
```

### Step 5: Open in Browser
- Navigate to: `http://localhost:5173/`
- You should see the CRM login page
- Try logging in with: `AymenAdmin` / `Admin@2026`

---

## Building for Production

### Step 1: Ensure `.env` is Correct
Verify the production API URL in `.env`:
```env
VITE_API_BASE_URL=https://crm.ttshop.pro/code_source/backend/php
```

### Step 2: Run Production Build
```bash
npm run build
```

**What this does:**
- Compiles TypeScript to JavaScript
- Minifies & optimizes all assets
- Creates optimized `dist/` folder
- Generates source maps for debugging

**Output:**
```
vite v7.3.1 building for production...
✓ 1234 modules transformed
dist/index.html                    12.34 kB │ gzip: 3.21 kB
dist/assets/index-xxxxx.js        456.78 kB │ gzip: 98.76 kB
dist/assets/index-xxxxx.css        45.67 kB │ gzip: 8.90 kB

✓ build complete
```

### Step 3: Verify Build Output
```bash
dir dist/
```

Should contain:
- `index.html` - Main entry point
- `assets/` - Folder with JS/CSS bundles
- `public/version.json` - Version stamp

---

## Deployment to Server

### Option A: Deploy via FTP/SFTP

#### Step 1: Prepare Files
```bash
# Build locally first
npm run build

# Files to upload:
# - dist/* → /code_source/
# - .env → /code_source/.env (on server)
# - .htaccess → /code_source/.htaccess
# - backend/php/* → /code_source/backend/php/
```

#### Step 2: Upload to Server
Using an FTP client (FileZilla, WinSCP, etc.):

1. Connect to: `ttshop.pro`
2. Navigate to: `/public_html/code_source/`
3. Upload contents of `dist/` folder
4. Upload `.env` file
5. Upload `.htaccess` file
6. Verify `backend/php/` folder exists with all PHP files

#### Step 3: Verify Deployment
```bash
# Test health endpoint
curl https://crm.ttshop.pro/code_source/backend/php/health.php

# Test login
curl -X POST https://crm.ttshop.pro/code_source/backend/php/auth_login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"AymenAdmin","password":"Admin@2026"}'
```

### Option B: Deploy via Git Push (if server has Git)

#### Step 1: Initialize Git (if not already done)
```bash
git init
git add .
git commit -m "Initial commit"
```

#### Step 2: Add Remote (on server)
```bash
# On your server, create a bare repository
ssh user@ttshop.pro
mkdir -p /var/repo/code_source.git
cd /var/repo/code_source.git
git init --bare
```

#### Step 3: Create Post-Hook
```bash
cat > /var/repo/code_source.git/hooks/post-receive << 'EOF'
#!/bin/bash
GIT_WORK_TREE=/var/www/code_source git checkout -f
cd /var/www/code_source
npm install --production
npm run build
EOF

chmod +x /var/repo/code_source.git/hooks/post-receive
```

#### Step 4: Push to Server
```bash
git remote add production ssh://user@ttshop.pro/var/repo/code_source.git
git push production main
```

---

## Environment Configuration

### Development (Local)
**File: `.env.development`**
```env
VITE_API_BASE_URL=http://localhost:8000/backend/php
```

**Run:**
```bash
npm run dev
```

### Staging
**File: `.env.staging`**
```env
VITE_API_BASE_URL=https://crm.ttshop.pro/code_source/backend/php
```

**Build:**
```bash
npm run build -- --mode staging
```

### Production
**File: `.env.production` or `.env`**
```env
VITE_API_BASE_URL=https://crm.ttshop.pro/code_source/backend/php
```

**Build:**
```bash
npm run build -- --mode production
```

---

## Available npm Commands

```bash
# Development
npm run dev              # Start dev server (hot reload)

# Production Build
npm run build            # Production build (minified)
npm run build:dev       # Development build (for debugging)

# Preview
npm run preview         # Preview production build locally

# Code Quality
npm run lint            # Check code for errors
npm run format          # Auto-format code with Prettier

# Cleaning
npm run clean           # Remove dist/ folder (manual)
rm -r dist              # (or use this on Linux/Mac)
```

---

## Project Structure

```
code_source/
├── src/
│   ├── components/      # React components
│   ├── hooks/          # Custom React hooks
│   ├── lib/            # Utilities (api.ts, auth.tsx, etc.)
│   ├── routes/         # Page components & routing
│   ├── assets/         # Images, fonts, etc.
│   ├── main.tsx        # React entry point
│   ├── router.tsx      # Route definitions
│   └── styles.css      # Global styles
├── backend/
│   └── php/            # PHP API endpoints
│       ├── config.php          # DB config
│       ├── auth_login.php      # Authentication
│       ├── prospects.php       # Prospect management
│       ├── contracts.php       # Contract management
│       └── ... (80+ endpoints)
├── public/             # Static assets
├── dist/               # Build output (generated)
├── .env                # Environment variables
├── .htaccess           # Apache routing config
├── package.json        # Dependencies & scripts
├── tsconfig.json       # TypeScript config
├── vite.config.ts      # Vite build config
└── README.md           # Documentation
```

---

## Complete Step-by-Step Deployment

### For Beginners:

**1. Clone the project (on your machine)**
```bash
# Copy entire folder to a safe location
# Or use Git if available
```

**2. Install dependencies**
```bash
cd code_source
npm install
```

**3. Test locally**
```bash
npm run dev
# Open http://localhost:5173/ in browser
```

**4. Build for production**
```bash
npm run build
# Creates dist/ folder with optimized files
```

**5. Upload to server**
```
Using FTP:
- Upload contents of dist/ → /code_source/
- Upload .env → /code_source/
- Upload .htaccess → /code_source/
- Verify backend/php/ files exist
```

**6. Test on server**
```bash
# Visit https://crm.ttshop.pro/code_source/
# Login with: AymenAdmin / Admin@2026
```

---

## Performance Optimization Tips

### Before Build
1. **Remove unused dependencies:**
   ```bash
   npm prune
   ```

2. **Update all packages:**
   ```bash
   npm update
   ```

3. **Check build size:**
   ```bash
   npm run build
   # Check dist/ folder size
   ```

### After Build
1. **Enable compression on server:**
   - Already configured in `.htaccess` (gzip)
   - Reduces file size by 70-80%

2. **Enable browser caching:**
   - Already configured in `.htaccess`
   - Static assets cached for 30 days

3. **Use CDN (optional):**
   - Move `dist/assets/` to CDN
   - Update asset paths in `vite.config.ts`

---

## Troubleshooting

### Issue: `npm: command not found`
**Solution:** Install Node.js from [nodejs.org](https://nodejs.org)

### Issue: `ENOENT: no such file or directory`
**Solution:**
```bash
npm install
npm cache clean --force
npm install
```

### Issue: Port 5173 already in use
**Solution:**
```bash
# Use different port
npm run dev -- --port 3000
# Open http://localhost:3000/
```

### Issue: API calls return 404
**Solution:** Verify `.env` has correct API URL:
```bash
# Check .env file
cat .env

# Should show:
# VITE_API_BASE_URL=https://crm.ttshop.pro/code_source/backend/php
```

### Issue: `build` folder is too large
**Solution:** Check `node_modules/` size:
```bash
npm run build

# Don't deploy node_modules/!
# Only deploy dist/ folder
```

### Issue: Login page appears but can't login
**Solution:**

1. Verify backend is running:
   ```bash
   curl https://crm.ttshop.pro/code_source/backend/php/health.php
   ```

2. Check database connection:
   - Verify `config.php` has correct DB credentials
   - Test: `wordpress_18` database exists
   - User: `ttshopvente` has permissions

3. Check CORS headers:
   ```bash
   curl -i https://crm.ttshop.pro/code_source/backend/php/health.php
   # Should see: Access-Control-Allow-Origin: *
   ```

### Issue: Build takes too long
**Solution:**
```bash
# Clear cache
rm -rf node_modules
npm cache clean --force
npm install
npm run build
```

---

## Security Checklist

Before going to production:

- ✅ Change default credentials (AymenAdmin/Admin@2026)
- ✅ Remove `.env` from Git history
- ✅ Use HTTPS (not HTTP)
- ✅ Update `Access-Control-Allow-Origin` in `.htaccess`
- ✅ Enable database backups
- ✅ Set strong passwords for `ttshopvente` user
- ✅ Disable direct access to PHP files (if not needed)
- ✅ Keep dependencies updated: `npm update`
- ✅ Review `.htaccess` security rules
- ✅ Monitor server logs for errors

---

## Additional Resources

- **Vite Documentation:** https://vitejs.dev/
- **React Router:** https://reactrouter.com/
- **TanStack Query:** https://tanstack.com/query/
- **Tailwind CSS:** https://tailwindcss.com/
- **PHP Best Practices:** https://www.php.net/

---

## Quick Reference Card

```bash
# Development
npm install              # Install dependencies
npm run dev             # Start dev server
npm run lint            # Check code
npm run format          # Format code

# Production
npm run build           # Build for production
npm run preview         # Test build locally

# Deployment
# 1. npm run build
# 2. Upload dist/* to server
# 3. Verify .env and .htaccess
# 4. Test at https://crm.ttshop.pro/code_source/
```

---

## Support

If you encounter issues:

1. Check this guide's **Troubleshooting** section
2. Review console errors (F12 → Console tab)
3. Check server logs (contact hosting provider)
4. Verify `.env` configuration
5. Test API endpoints with curl

---

**Last Updated:** May 30, 2026
**API Version:** 1.0.0
**Frontend:** React 19.2 + Vite 7.3
**Backend:** PHP 8.0+ + MySQL
