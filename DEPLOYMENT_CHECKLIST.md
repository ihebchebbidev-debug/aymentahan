# 📋 Pre-Deployment Checklist

## ✅ Configuration Verification

### Environment Files
- [ ] `.env` exists in project root
- [ ] `.env` contains: `VITE_API_BASE_URL=http://www.api-test.animacom.com.tn/code_source/backend/php`
- [ ] `.htaccess` exists in project root
- [ ] `.htaccess` excludes `backend/php/` from rewriting

### Backend Configuration
- [ ] `backend/php/config.php` has correct database credentials:
  - [ ] Host: `localhost`
  - [ ] Database: `wordpress_18`
  - [ ] User: `ttshopvente`
  - [ ] Password: `8Jjs%1g23`
- [ ] Database `wordpress_18` exists on server
- [ ] Database user `ttshopvente` has permissions

### API Verification
- [ ] Health endpoint works:
  ```bash
  curl http://www.api-test.animacom.com.tn/code_source/backend/php/health.php
  ```
  Should return JSON with `"success": true`

- [ ] Login endpoint works:
  ```bash
  curl -X POST http://www.api-test.animacom.com.tn/code_source/backend/php/auth_login.php \
    -H "Content-Type: application/json" \
    -d '{"username":"AymenAdmin","password":"Admin@2026"}'
  ```
  Should return token

---

## ✅ Build Verification

### Dependencies
- [ ] Ran `npm install`
- [ ] No errors during installation
- [ ] `node_modules/` folder created
- [ ] `package-lock.json` generated

### Build Process
- [ ] Ran `npm run build`
- [ ] Build completed without errors
- [ ] `dist/` folder created
- [ ] `dist/index.html` exists
- [ ] `dist/assets/` folder exists with JS/CSS files

### Build Output
- [ ] Total size < 1 MB
- [ ] No broken imports
- [ ] No console errors in build log

---

## ✅ Local Testing

### Development Server
- [ ] Ran `npm run dev`
- [ ] Server started on http://localhost:5173/
- [ ] No console errors

### Login Testing
- [ ] Opened http://localhost:5173/
- [ ] Login page displayed
- [ ] Entered credentials: `AymenAdmin` / `Admin@2026`
- [ ] Successfully logged in
- [ ] Dashboard loaded
- [ ] Can navigate between pages

### API Testing (Local)
- [ ] Prospects page loads data
- [ ] Contracts page loads data
- [ ] Dashboard shows KPIs
- [ ] Can create/edit items

---

## ✅ Server Preparation

### File Structure
- [ ] Server path exists: `/code_source/`
- [ ] Subdirectory exists: `/code_source/backend/php/`
- [ ] All PHP files in `backend/php/`:
  - [ ] `health.php`
  - [ ] `auth_login.php`
  - [ ] `config.php`
  - [ ] All other endpoint files (~80+ files)

### Server Configuration
- [ ] Apache mod_rewrite enabled
- [ ] `.htaccess` supported
- [ ] PHP 8.0+ installed
- [ ] PHP extensions: PDO, MySQL
- [ ] CORS headers can be set

### Database
- [ ] MySQL server running
- [ ] Database `wordpress_18` created
- [ ] User `ttshopvente` created
- [ ] User has `ALL PRIVILEGES` on `wordpress_18`
- [ ] Connection tested successfully

---

## ✅ Deployment Steps

### Upload Files
- [ ] Connected to server via FTP/SFTP
- [ ] Navigated to: `/public_html/code_source/`
- [ ] Uploaded all files from `dist/` folder
- [ ] Uploaded `.env` file
- [ ] Uploaded `.htaccess` file
- [ ] Verified file permissions (644 for files, 755 for folders)

### Post-Upload Verification
- [ ] `index.html` exists on server
- [ ] `assets/` folder exists with files
- [ ] `.env` file exists on server
- [ ] `.htaccess` file exists on server
- [ ] Backend PHP files exist

---

## ✅ Server Testing

### Health Check
- [ ] Endpoint returns JSON:
  ```bash
  curl http://www.api-test.animacom.com.tn/code_source/backend/php/health.php
  ```

### Frontend Access
- [ ] Can access http://www.api-test.animacom.com.tn/code_source/
- [ ] Page loads (not 404)
- [ ] No CORS errors in console
- [ ] CSS/JS assets load (check Network tab)

### Authentication
- [ ] Login page displays
- [ ] Can login with: `AymenAdmin` / `Admin@2026`
- [ ] Token received and stored
- [ ] Redirected to dashboard

### Data Loading
- [ ] Dashboard loads KPIs
- [ ] Prospects page loads
- [ ] Contracts page loads
- [ ] Users can navigate without errors

### API Calls
- [ ] XHR requests show 200 status
- [ ] Response includes `"success": true`
- [ ] No 403/401/404 errors
- [ ] Data displays correctly

---

## ✅ Security Checks

- [ ] `.env` file not accessible via browser
- [ ] `backend/php/` not directly browseable
- [ ] CORS properly configured
- [ ] Default credentials still used (CHANGE BEFORE PRODUCTION)
- [ ] HTTPS enabled (if required)
- [ ] PHP error display disabled
- [ ] Logs not publicly accessible

---

## ✅ Performance Checks

- [ ] Page load time < 5 seconds
- [ ] API responses < 1 second
- [ ] Gzip compression working
- [ ] Static assets cached
- [ ] No console errors/warnings
- [ ] Memory usage reasonable

---

## ✅ Browser Compatibility

Test in:
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Edge

Each should:
- [ ] Load without errors
- [ ] Display all content
- [ ] Forms work
- [ ] Navigation works

---

## ✅ Final Checks

- [ ] Created backup of all files
- [ ] Documented any custom changes
- [ ] Updated `.env` comments
- [ ] Tested rollback procedure
- [ ] Notified team of deployment
- [ ] Monitored logs for 1 hour
- [ ] All users can login successfully

---

## 🚨 Emergency Rollback Plan

If something goes wrong:

1. **Stop current deployment:**
   ```bash
   # Delete dist/ contents from server
   # Restore previous version if available
   ```

2. **Check API:**
   ```bash
   curl http://www.api-test.animacom.com.tn/code_source/backend/php/health.php
   ```

3. **Check database:**
   - Verify connection in `config.php`
   - Check user credentials

4. **Check `.env`:**
   - Verify API URL
   - Verify it's not corrupted

5. **Check logs:**
   - Server error logs
   - Browser console (F12)
   - PHP error log

6. **Contact support if:**
   - Database is unreachable
   - Server is down
   - Files won't upload

---

## 📞 Support Contacts

- **Hosting Provider:** [Your Host]
- **Server Admin:** [Your Admin]
- **Database Admin:** [Your DBA]

---

## Notes

Add any project-specific notes here:

```
Last deployment: [Date]
Deployed by: [Name]
Version: [Version number]
Changes made: [List changes]
Issues encountered: [List any issues]
Resolution: [How issues were resolved]
```

---

**Status:** ✅ Ready to Deploy / ❌ Not Ready (list blockers)

**Approval:** _________________ (Signature/Initials)

**Date:** _____________________
