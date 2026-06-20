# 📚 Complete API Endpoints Documentation

**Base URL:** `http://www.api-test.animacom.com.tn/code_source/backend/php`

---

## ✅ Authentication APIs

| Endpoint | Method | Description | Auth Required |
|----------|--------|-------------|----------------|
| `/health.php` | GET | Health check - API status | ❌ No |
| `/auth_login.php` | POST | Login with username/password | ❌ No |
| `/auth_me.php` | GET | Get current user profile | ✅ Yes |
| `/auth_logout.php` | POST | Logout and invalidate token | ✅ Yes |
| `/auth_signup.php` | POST | Create new user account | ⚠️ Restricted |
| `/auth_change_password.php` | POST | Change user password | ✅ Yes |
| `/auth_update_profile.php` | POST | Update user profile | ✅ Yes |
| `/auth_otp_resend.php` | POST | Resend OTP code | ❌ No |
| `/auth_otp_verify.php` | POST | Verify OTP code | ❌ No |
| `/auth_admin_reset_password.php` | POST | Admin reset user password | ✅ Admin Only |

---

## 💼 Core Business APIs

### Prospects
| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/prospects.php` | GET | List all prospects | ✅ |
| `/prospects.php` | POST | Create new prospect | ✅ |
| `/prospects.php?id=X` | PATCH | Update prospect | ✅ |
| `/prospects.php?id=X` | DELETE | Delete prospect | ✅ |
| `/prospects.php?action=claim` | POST | Claim prospect | ✅ |
| `/prospects.php?action=mark_won` | POST | Mark prospect as won | ✅ |
| `/prospects.php?action=mark_lost` | POST | Mark prospect as lost | ✅ |

### Contracts
| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/contracts.php` | GET | List all contracts | ✅ |
| `/contracts.php` | POST | Create new contract | ✅ |
| `/contracts.php?id=X` | PATCH | Update contract | ✅ |
| `/contracts.php?id=X` | DELETE | Delete contract | ✅ |
| `/contract_info.php` | GET | Get contract information | ✅ |
| `/contract_stages.php` | GET | Get contract pipeline stages | ✅ |

### Opportunities
| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/opportunities.php` | GET | List opportunities | ✅ |
| `/opportunities.php` | POST | Create opportunity | ✅ |
| `/opportunities.php?id=X` | PATCH | Update opportunity | ✅ |
| `/opportunities.php?id=X` | DELETE | Delete opportunity | ✅ |

---

## 👥 User & Role Management

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/users.php` | GET | List all users | ✅ |
| `/users.php` | POST | Create new user | ✅ Admin |
| `/users.php?id=X` | PATCH | Update user | ✅ Admin |
| `/users.php?id=X` | DELETE | Delete user | ✅ Admin |
| `/roles.php` | GET | Get roles & permissions matrix | ✅ |
| `/roles.php?action=update` | PUT | Update role permissions | ✅ Admin |
| `/roles.php?action=assign` | POST | Assign role to user | ✅ Admin |
| `/roles.php?action=delete` | DELETE | Delete role | ✅ Admin |

---

## 📋 Activity & History

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/activity.php` | GET | Get activity log (filterable) | ✅ |
| `/lead_actions.php` | GET | Get lead action history | ✅ |
| `/audit_log.php` | GET | Get audit trail | ✅ Admin |

---

## 📊 Reporting & Analytics

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/dashboard.php` | GET | Get KPIs & dashboard data | ✅ |
| `/reports.php` | GET | Get detailed reports (JSON/CSV) | ✅ |

---

## 🏢 Administrative

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/settings.php` | GET | Get global settings | ✅ |
| `/settings.php` | PUT | Update global settings | ✅ Admin |
| `/ip_allowlist.php` | GET | Get IP whitelist | ✅ Admin |
| `/ip_allowlist.php` | POST/DELETE | Manage IP allowlist | ✅ Admin |
| `/idle_timeouts.php` | GET | Get idle timeout config | ✅ |
| `/idle_timeouts.php` | POST/PUT/DELETE | Manage idle timeouts | ✅ Admin |

---

## 📎 Attachments & Files

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/attachments.php` | GET | List attachments | ✅ |
| `/attachments.php` | POST | Upload file (multipart) | ✅ |
| `/attachments.php?id=X` | DELETE | Delete attachment | ✅ |
| `/attachments.php?download=X` | GET | Download file with token | ✅ |

---

## 🗂️ Custom Fields

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/custom_fields.php` | GET | List custom field definitions | ✅ |
| `/custom_fields.php` | POST | Create custom field | ✅ Admin |
| `/custom_fields.php?id=X` | PUT | Update custom field | ✅ Admin |
| `/custom_fields.php?id=X` | DELETE | Delete custom field | ✅ Admin |
| `/custom_field_values.php` | GET | Get custom field values | ✅ |
| `/custom_field_values.php` | POST | Set custom field values | ✅ |

---

## 📅 Calendar & Events

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/calendar.php` | GET | List calendar events | ✅ |
| `/calendar.php` | POST | Create event | ✅ |
| `/calendar.php?id=X` | PUT | Update event | ✅ |
| `/calendar.php?id=X` | DELETE | Delete event | ✅ |

---

## 💬 Communication

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/chat.php` | GET | Get chat messages | ✅ |
| `/chat.php` | POST | Send message | ✅ |
| `/notifications.php` | GET | Get notifications | ✅ |
| `/notifications.php` | POST | Create notification | ✅ |
| `/notifications.php?id=X` | PATCH | Mark notification read | ✅ |

---

## ⏱️ Attendance & Time Tracking

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/attendance.php?action=clock_in` | POST | Clock in | ✅ |
| `/attendance.php?action=clock_out` | POST | Clock out | ✅ |
| `/attendance.php` | GET | Get attendance records | ✅ |

---

## 🌍 Guichet (Service Window) APIs

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/guichet_entities.php` | GET | List guichet entities | ✅ |
| `/guichet_entities.php` | POST | Create entity | ✅ Admin |
| `/guichet_dossiers.php` | GET | List dossiers | ✅ |
| `/guichet_dossiers.php` | POST | Create dossier | ✅ |
| `/guichet_dossiers.php?action=validate` | POST | Validate dossier | ✅ |
| `/guichet_entries.php` | GET | List entries | ✅ |
| `/guichet_entries.php` | POST | Create entry | ✅ |
| `/guichet_entries.php` | PATCH | Update entry | ✅ |
| `/guichet_entries.php?id=X` | DELETE | Delete entry | ✅ |
| `/guichet_objectives.php` | GET | Get objectives | ✅ |

---

## 🧹 Utility & Maintenance

| Endpoint | Method | Description | Auth |
|----------|--------|-------------|------|
| `/health.php` | GET | Health check | ❌ |
| `/filter_presets.php` | GET | Get saved filter presets | ✅ |
| `/filter_presets.php` | POST | Save filter preset | ✅ |
| `/external_agents.php` | GET | List external agents | ✅ Admin |

---

## 📌 Quick Test Commands

### Test Health
```bash
curl http://www.api-test.animacom.com.tn/code_source/backend/php/health.php
```

### Test Login
```bash
curl -X POST http://www.api-test.animacom.com.tn/code_source/backend/php/auth_login.php \
  -H "Content-Type: application/json" \
  -d '{"username":"AymenAdmin","password":"Admin@2026"}'
```

### Test Get User (requires token from login)
```bash
curl -X GET "http://www.api-test.animacom.com.tn/code_source/backend/php/auth_me.php" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Auth-Token: YOUR_TOKEN"
```

### Test Get Prospects
```bash
curl -X GET "http://www.api-test.animacom.com.tn/code_source/backend/php/prospects.php" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Auth-Token: YOUR_TOKEN"
```

### Test Get Dashboard
```bash
curl -X GET "http://www.api-test.animacom.com.tn/code_source/backend/php/dashboard.php" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "X-Auth-Token: YOUR_TOKEN"
```

---

## 🔐 Authentication Header Format

All authenticated endpoints require:
```
Authorization: Bearer <JWT_TOKEN>
X-Auth-Token: <JWT_TOKEN>
Content-Type: application/json
```

---

## ✨ Configuration Summary

- **Base URL:** `http://www.api-test.animacom.com.tn/code_source/backend/php`
- **Database:** `wordpress_18` on `localhost`
- **DB User:** `ttshopvente`
- **Frontend Base:** `http://www.api-test.animacom.com.tn/code_source/`
- **CORS:** Enabled for all origins
- **JWT:** Token-based authentication
