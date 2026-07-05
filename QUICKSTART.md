# ⚡ Quick Start Reference

## For Windows Users - Easiest Way

**Double-click:** `setup.bat`

This opens an interactive menu where you can:
- 1️⃣ Install dependencies
- 2️⃣ Start development server
- 3️⃣ Build for production
- 4️⃣ Format code
- 5️⃣ Lint code
- 6️⃣ Clean build files

---

## For Mac/Linux Users

```bash
# Make setup script executable
chmod +x setup.sh

# Run setup
./setup.sh
```

---

## Essential Commands

### First Time Setup (Do This Once)
```bash
npm install
```

### Development (For Testing Locally)
```bash
npm run dev
```
Then open: http://localhost:5173/

### Build for Production (Before Uploading to Server)
```bash
npm run build
```
This creates a `dist/` folder with optimized files.

### Upload to Server (Using FTP)
1. Build first: `npm run build`
2. Upload contents of `dist/` folder to: `/code_source/`
3. Upload `.env` file to: `/code_source/.env`
4. Upload `.htaccess` file to: `/code_source/.htaccess`
5. Verify `backend/php/` folder exists with PHP files

### Test on Server
```bash
# Open in browser
https://crm.ttshop.pro/code_source/

# Test API
curl https://crm.ttshop.pro/code_source/backend/php/health.php
```

---

## File Locations

| File/Folder | Purpose | Location |
|------------|---------|----------|
| `src/` | React code | Local |
| `backend/php/` | PHP API | Local + Server |
| `dist/` | Production build | Generated, upload to server |
| `.env` | Configuration | Local + Server |
| `.htaccess` | Routing | Local + Server |
| `package.json` | Dependencies | Local only |
| `node_modules/` | Installed packages | Local only (don't upload!) |

---

## Environment Configuration

### What is `.env`?
A file that stores configuration variables (like API URL).

### Current Configuration
```env
VITE_API_BASE_URL=https://crm.ttshop.pro/code_source/backend/php
```

### For Local Testing
```env
VITE_API_BASE_URL=http://localhost:8000/backend/php
```

### For Production
```env
VITE_API_BASE_URL=https://crm.ttshop.pro/code_source/backend/php
```

---

## Troubleshooting Quick Fixes

| Problem | Solution |
|---------|----------|
| `npm: command not found` | Install Node.js from https://nodejs.org/ |
| Port 5173 in use | Run: `npm run dev -- --port 3000` |
| Build fails | Run: `npm install` then `npm run build` |
| API returns 404 | Check `.env` has correct API URL |
| Login fails | Verify backend/php/health.php returns JSON |
| Too many files to upload | Only upload `dist/` folder, not `node_modules/` |

---

## Deployment Checklist

Before uploading to production:

- [ ] Ran `npm run build`
- [ ] `dist/` folder created
- [ ] `.env` updated with production URL
- [ ] `.htaccess` file in place
- [ ] `backend/php/` folder with all PHP files
- [ ] Tested API endpoints with curl
- [ ] Verified health.php returns JSON
- [ ] Database credentials correct in `config.php`
- [ ] Can login with test account

---

## Login Credentials

**Username:** `AymenAdmin`
**Password:** `Admin@2026`

---

## File Upload Instructions (FTP)

### Using FileZilla:
1. Connect to: `ttshop.pro`
2. Username: (your FTP username)
3. Password: (your FTP password)
4. Navigate to: `/public_html/code_source/`
5. Drag & drop files from `dist/` folder

### Using WinSCP:
1. Hostname: `ttshop.pro`
2. Protocol: SFTP
3. Upload `dist/` contents to: `/code_source/`

---

## Common Tasks

### Update Code
```bash
# Edit files in src/
# (Changes auto-reload if npm run dev is running)

# When done, rebuild
npm run build
```

### Update Dependencies
```bash
npm update
npm run build
```

### Add New Package
```bash
npm install package-name
npm run build
```

### Check for Issues
```bash
npm run lint
```

### Fix Formatting
```bash
npm run format
```

---

## What Gets Built?

When you run `npm run build`, it creates:

```
dist/
├── index.html              # Main page (~12 KB)
├── assets/
│   ├── index-xxxxx.js      # JavaScript bundle (~457 KB)
│   └── index-xxxxx.css     # Styles (~46 KB)
└── public/
    └── version.json        # Version info
```

**Total size:** ~500 KB (compressed to ~100 KB with gzip)

---

## After Deployment

### Monitor the Site
```bash
# Test health
curl https://crm.ttshop.pro/code_source/backend/php/health.php

# Check if you can login
# Open: https://crm.ttshop.pro/code_source/
```

### If Something Breaks
1. Check browser console (F12 → Console)
2. Check server logs
3. Verify API endpoints are working
4. Check `.env` configuration
5. Rebuild and redeploy

---

## Next Steps

1. ✅ Install dependencies: `npm install`
2. ✅ Test locally: `npm run dev`
3. ✅ Build: `npm run build`
4. ✅ Upload to server via FTP
5. ✅ Test on server
6. ✅ Done!

---

For detailed information, see:
- 📘 `SETUP_DEPLOYMENT_GUIDE.md` - Complete guide
- 📋 `API_ENDPOINTS.md` - All API endpoints
- 📖 `README.md` - Project overview
