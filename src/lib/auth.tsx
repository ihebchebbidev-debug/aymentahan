import { createContext, useCallback, useContext, useEffect, useRef, useState, type ReactNode } from "react";
import { api, API_ENABLED, getToken, setToken, authenticatedApiUrl } from "./api";

export type AuthUser = {
  id: string;
  username: string;
  fullName: string;
  email: string;
  role: "Administrateur" | "Manager" | "Agent" | "Backoffice" | "AgentSuivi" | "AgentActivation" | "AgentVente" | string;
  team: string;
  active: boolean;
  mustChangePassword?: boolean;
  grantedRoles?: string[];
  grantedPermissions?: string[];
  allowedPermissions?: string[];
  deniedPermissions?: string[];
  // HR / personnel
  jobTitle?: string | null;
  birthDate?: string | null;
  cin?: string | null;
  company?: string | null;
  contractType?: string | null;
  salary?: number | null;
  salaryIncrease?: number | null;
  contractStart?: string | null;
  contractEnd?: string | null;
  renewalStart?: string | null;
  renewalEnd?: string | null;
  observations?: string | null;
  phone?: string | null;
  rib?: string | null;
  hireDate?: string | null;
  guichetEntityId?: string | null;
  teamId?: string | null;
  teamName?: string | null;
  teamRoles?: string[];
};

export type SignupInput = {
  username: string;
  fullName: string;
  email: string;
  password: string;
  team?: string;
};

export type OtpChallenge = {
  challenge: string;
  maskedEmail: string;
  expiresAt: string;
  codeLength: number;
};

export type EmailChangeRequired = {
  emailChangeRequired: true;
  currentEmail: string;
  message: string;
};

export type LoginResult =
  | { kind: "otp"; challenge: OtpChallenge }
  | { kind: "emailChange"; data: EmailChangeRequired }
  | { kind: "done" };

type AuthState = {
  user: AuthUser | null;
  loading: boolean;
  permissionsLoading: boolean;
  permissionsHydrated: boolean;
  apiEnabled: boolean;
  permissions: Record<string, boolean>;
  hasPermission: (key: string) => boolean;
  refreshPermissions: () => Promise<void>;
  login: (username: string, password: string, newEmail?: string) => Promise<LoginResult>;
  verifyOtp: (challenge: string, code: string) => Promise<void>;
  resendOtp: (challenge: string) => Promise<{ expiresAt: string; codeLength?: number }>;
  signup: (input: SignupInput) => Promise<void>;
  changePassword: (currentPassword: string, newPassword: string) => Promise<void>;
  updateProfile: (patch: Partial<AuthUser>) => Promise<AuthUser>;
  clearMustChange: () => void;
  logout: () => void;
};

const AuthContext = createContext<AuthState | null>(null);

// Legacy permission-cache prefix. We only use it for cleanup now: permissions
// must come from the backend on each session, never from localStorage, otherwise
// a user whose role was emptied can keep stale page access from a previous login.
const PERMS_CACHE_PREFIX = "erp_perms_cache_";

// ---------------------------------------------------------------------
// Live permission sync
// Any part of the app (or another browser tab) can signal that permissions
// changed. Every AuthProvider instance listening re-fetches immediately, so
// a user receives new/revoked permissions without logging out and back in.
// ---------------------------------------------------------------------
export const PERMISSIONS_CHANGED_EVENT = "erp:permissions-changed";
const PERMS_SYNC_STORAGE_KEY = "erp_perms_sync_ping";

export function notifyPermissionsChanged() {
  try { window.dispatchEvent(new Event(PERMISSIONS_CHANGED_EVENT)); } catch { /* ignore */ }
  // Cross-tab: the `storage` event fires in every *other* tab of this origin.
  try { localStorage.setItem(PERMS_SYNC_STORAGE_KEY, String(Date.now())); } catch { /* ignore */ }
}

function isPlainObject(x: unknown): x is Record<string, unknown> {
  return !!x && typeof x === "object" && !Array.isArray(x);
}
function collectTruePermissions(source: unknown): Record<string, boolean> {
  const out: Record<string, boolean> = {};
  if (!isPlainObject(source)) return out;
  for (const [k, v] of Object.entries(source)) {
    if (v === true) out[k] = true;
  }
  return out;
}
function hasAnyTrue(source: Record<string, boolean>): boolean {
  return Object.values(source).some((v) => v === true);
}

function lookupRolePerms(
  permsMap: Record<string, unknown>,
  roleName: string | undefined | null,
): Record<string, boolean> {
  if (!roleName) return {};
  // Try exact match first, then case-insensitive — the backend has historically
  // had inconsistent casing for role names (e.g. "HumainResource" vs "HumainRessource").
  if (permsMap[roleName] !== undefined) return collectTruePermissions(permsMap[roleName]);
  const lower = roleName.toLowerCase();
  for (const [k, v] of Object.entries(permsMap)) {
    if (k.toLowerCase() === lower) return collectTruePermissions(v);
  }
  return {};
}

async function loadPermissionsForUser(
  user: AuthUser,
  fallbackPerms?: Record<string, boolean>,
): Promise<Record<string, boolean>> {
  if (user.role === "Administrateur") {
    return new Proxy({} as Record<string, boolean>, { get: () => true }) as any;
  }
  try {
    const r = await api<{
      permissions?: Record<string, Record<string, boolean> | unknown[]> | null;
      effectivePermissions?: Record<string, boolean> | null;
    }>("/roles.php");

    const permsMap = isPlainObject(r?.permissions) ? r.permissions as Record<string, unknown> : {};
    const ownRolePerms = lookupRolePerms(permsMap, user.role);
    const grantedRolePerms: Record<string, boolean> = {};
    for (const role of user.grantedRoles ?? []) {
      Object.assign(grantedRolePerms, lookupRolePerms(permsMap, role));
    }

    if (!hasAnyTrue(ownRolePerms) && (!fallbackPerms || !hasAnyTrue(fallbackPerms))) {
      const scoped: Record<string, boolean> = { ...grantedRolePerms };
      for (const p of user.grantedPermissions ?? []) scoped[p] = true;
      for (const p of user.allowedPermissions ?? []) scoped[p] = true;
      for (const p of user.deniedPermissions ?? []) scoped[p] = false;
      if (import.meta.env?.DEV) {
        console.info("[auth] perms hydrated (empty own role)", {
          user: user.username, role: user.role,
          granted: Object.keys(scoped).filter((k) => scoped[k]),
        });
      }
      return scoped;
    }

    if (isPlainObject(r?.effectivePermissions)) {
      const eff: Record<string, boolean> = {};
      for (const [k, v] of Object.entries(r.effectivePermissions as Record<string, unknown>)) {
        if (typeof v === "boolean") eff[k] = v;
      }
      for (const p of user.allowedPermissions ?? []) eff[p] = true;
      for (const p of user.deniedPermissions ?? []) eff[p] = false;
      if (import.meta.env?.DEV) {
        console.info("[auth] perms hydrated (effective)", {
          user: user.username, role: user.role,
          count: Object.keys(eff).filter((k) => eff[k]).length,
        });
      }
      return eff;
    }

    const base: Record<string, boolean> = { ...fallbackPerms, ...ownRolePerms, ...grantedRolePerms };
    for (const p of user.grantedPermissions ?? []) base[p] = true;
    for (const p of user.allowedPermissions ?? []) base[p] = true;
    for (const p of user.deniedPermissions ?? []) base[p] = false;
    if (import.meta.env?.DEV) {
      console.info("[auth] perms hydrated (fallback)", {
        user: user.username, role: user.role,
        count: Object.keys(base).filter((k) => base[k]).length,
      });
    }
    return base;
  } catch (e: any) {
    console.warn("[auth] /roles.php failed, preserving initial user permissions", {
      status: e?.status, message: e?.message,
    });
    if (fallbackPerms && Object.keys(fallbackPerms).length > 0) {
      return fallbackPerms;
    }
    const scoped: Record<string, boolean> = {};
    for (const p of user.grantedPermissions ?? []) scoped[p] = true;
    for (const p of user.allowedPermissions ?? []) scoped[p] = true;
    for (const p of user.deniedPermissions ?? []) scoped[p] = false;
    return scoped;
  }
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState<boolean>(API_ENABLED && !!getToken());
  const [permissions, setPermissions] = useState<Record<string, boolean>>({});
  const [permissionsLoading, setPermissionsLoading] = useState<boolean>(API_ENABLED && !!getToken());
  const [permissionsHydrated, setPermissionsHydrated] = useState<boolean>(false);

  const applyPermsForUser = useCallback(async (u: AuthUser | null, opts?: { silent?: boolean; fallbackPerms?: Record<string, boolean> }) => {
    const silent = opts?.silent === true;
    if (!u) {
      setPermissions({});
      setPermissionsLoading(false);
      setPermissionsHydrated(false);
      return;
    }
    if (!silent) setPermissionsLoading(true);
    try {
      const perms = await loadPermissionsForUser(u, opts?.fallbackPerms);
      if (import.meta.env?.DEV) {
        console.info('[auth] applyPermsForUser result', {
          user: u?.username, role: u?.role,
          fallbackCount: opts?.fallbackPerms ? Object.keys(opts.fallbackPerms).length : 0,
          permsCount: Object.keys(perms).filter(k => perms[k]).length,
        });
      }
      setPermissions(perms);
      setPermissionsHydrated(true);
    } finally {
      if (!silent) setPermissionsLoading(false);
    }
  }, []);

  useEffect(() => {
    if (!API_ENABLED) { setLoading(false); setPermissionsLoading(false); return; }
    const t = getToken();
    if (!t) { setLoading(false); setPermissionsLoading(false); return; }
    api<{ user: AuthUser; permissions?: Record<string, boolean>; effectivePermissions?: Record<string, boolean> }>("/auth_me.php")
      .then((r) => {
        setUser(r.user);
        setLoading(false);
        const mePerms = r.effectivePermissions ?? r.permissions;
        if (import.meta.env?.DEV) console.info('[auth] /auth_me.php returned', { user: r.user?.username, role: r.user?.role, mePermsCount: mePerms ? Object.keys(mePerms).length : 0 });
        void applyPermsForUser(r.user, { fallbackPerms: mePerms });
      })
      .catch((e: any) => {
        const status = Number(e?.status ?? 0);
        console.warn("[auth] /auth_me.php failed", { status, message: e?.message });
        if (status === 401) {
          setToken(null);
          setUser(null);
        }
        setLoading(false);
        setPermissionsLoading(false);
      });
  }, [applyPermsForUser]);

  // Best-effort: on tab close / refresh, try to send a beacon to close attendance.
  useEffect(() => {
    if (!API_ENABLED || !user) return;
    const onBeforeUnload = () => {
      try {
        if (typeof navigator !== "undefined" && typeof navigator.sendBeacon === "function") {
          const attUrl = authenticatedApiUrl("/attendance.php", { action: "clock_out" });
          navigator.sendBeacon(attUrl, "");
        }
      } catch { /* ignore */ }
    };
    window.addEventListener("beforeunload", onBeforeUnload);
    return () => window.removeEventListener("beforeunload", onBeforeUnload);
  }, [user]);

  // Keep the *whole session* fresh: re-fetch /auth_me.php (role, flags) and the
  // effective permission set. Used by the background poller, the focus/visibility
  // listeners and the explicit refreshPermissions() call, so an admin's change
  // (grant, revoke, role switch) reaches the user without a logout/login cycle.
  const syncSession = useCallback(async (opts?: { silent?: boolean }) => {
    const silent = opts?.silent !== false;
    if (!API_ENABLED || !getToken()) return;
    try {
      const me = await api<{
        user: AuthUser;
        permissions?: Record<string, boolean>;
        effectivePermissions?: Record<string, boolean>;
      }>("/auth_me.php");
      if (!me?.user) return;
      setUser((prev) => {
        // Only swap the object when something actually changed, to avoid
        // re-render loops in effects keyed on `user`.
        if (prev && JSON.stringify(prev) === JSON.stringify(me.user)) return prev;
        return me.user;
      });
      await applyPermsForUser(me.user, {
        silent,
        fallbackPerms: me.effectivePermissions ?? me.permissions,
      });
    } catch (e: any) {
      // A 401 means the session was revoked server-side — drop it.
      if (Number(e?.status ?? 0) === 401) {
        setToken(null);
        setUser(null);
        setPermissions({});
        setPermissionsHydrated(false);
      }
    }
  }, [applyPermsForUser]);

  // Always call the freshest syncSession from long-lived listeners.
  const syncRef = useRef(syncSession);
  useEffect(() => { syncRef.current = syncSession; }, [syncSession]);

  // Re-hydrate permissions whenever the identity or the role changes: an admin
  // moving a user from "Agent" to "Superviseur" must repaint the sidebar.
  const userId = user?.id ?? null;
  const userRole = user?.role ?? null;
  useEffect(() => {
    if (!API_ENABLED || !userId) return;
    void syncRef.current({ silent: true });
  }, [userId, userRole]);

  // Background freshness: focus / tab-visible / short poll / explicit signal.
  useEffect(() => {
    if (!API_ENABLED || !userId) return;
    let last = Date.now();
    const MIN_MS = 8_000; // throttle: at most one sync every 8 s
    const run = (force = false) => {
      if (!force && document.visibilityState !== "visible") return;
      const now = Date.now();
      if (!force && now - last < MIN_MS) return;
      last = now;
      void syncRef.current({ silent: true });
    };
    const onVisibility = () => run();
    const onFocus = () => run();
    const onSignal = () => run(true);
    const onStorage = (e: StorageEvent) => {
      if (e.key === PERMS_SYNC_STORAGE_KEY) run(true);
    };

    document.addEventListener("visibilitychange", onVisibility);
    window.addEventListener("focus", onFocus);
    window.addEventListener(PERMISSIONS_CHANGED_EVENT, onSignal);
    window.addEventListener("storage", onStorage);
    // Poll while the tab is visible so a grant lands within ~30 s on its own.
    const interval = window.setInterval(() => run(), 30_000);

    return () => {
      document.removeEventListener("visibilitychange", onVisibility);
      window.removeEventListener("focus", onFocus);
      window.removeEventListener(PERMISSIONS_CHANGED_EVENT, onSignal);
      window.removeEventListener("storage", onStorage);
      window.clearInterval(interval);
    };
  }, [userId]);


  const login = async (username: string, password: string, newEmail?: string): Promise<LoginResult> => {
    if (!API_ENABLED) {
      const u: AuthUser = {
        id: "U-DEMO", username: username || "demo", fullName: "Demo User",
        email: `${username || "demo"}@demo.local`, role: "Administrateur",
        team: "Direction", active: true,
      };
      setUser(u);
      await applyPermsForUser(u);
      return { kind: "done" };
    }
    const body: Record<string, string> = { username, password };
    if (newEmail?.trim()) body.newEmail = newEmail.trim();

    const r = await api<{
      success?: boolean;
      emailChangeRequired?: boolean;
      currentEmail?: string;
      message?: string;
      otpRequired?: boolean;
      challenge?: string;
      maskedEmail?: string;
      expiresAt?: string;
      codeLength?: number;
      token?: string;
      user?: AuthUser;
    }>("/auth_login.php", { method: "POST", body });

    if (r.emailChangeRequired === true) {
      return {
        kind: "emailChange",
        data: {
          emailChangeRequired: true,
          currentEmail: r.currentEmail ?? "admin@crminternet.local",
          message: r.message ?? "Veuillez renseigner votre adresse email réelle.",
        },
      };
    }

    if (r.otpRequired === true && r.challenge) {
      return {
        kind: "otp",
        challenge: {
          challenge: r.challenge,
          maskedEmail: r.maskedEmail ?? "",
          expiresAt: r.expiresAt ?? "",
          codeLength: Number(r.codeLength) > 0 ? Number(r.codeLength) : 4,
        },
      };
    }

    if (r.token && r.user) {
      setToken(r.token);
      setUser(r.user);
      try {
        const me = await api<{ user: AuthUser; permissions?: Record<string, boolean>; effectivePermissions?: Record<string, boolean> }>("/auth_me.php");
        setUser(me.user);
        const mePerms = me.effectivePermissions ?? me.permissions;
        if (import.meta.env?.DEV) console.info('[auth] login: /auth_me.php follow-up', { user: me.user?.username, role: me.user?.role, mePermsCount: mePerms ? Object.keys(mePerms).length : 0 });
        await applyPermsForUser(me.user, { fallbackPerms: mePerms });
      } catch {
        await applyPermsForUser(r.user);
      }
      api("/attendance.php?action=clock_in", { method: "POST", body: {} }).catch(() => {});
      return { kind: "done" };
    }

    throw new Error("Réponse de connexion invalide. Réessayez ou contactez l'administrateur.");
  };

  const verifyOtp = async (challenge: string, code: string) => {
    const r = await api<{ token: string; user: AuthUser }>("/auth_otp_verify.php", {
      method: "POST",
      body: { challenge, code },
    });
    setToken(r.token);
    setUser(r.user);
    try {
      const me = await api<{ user: AuthUser; permissions?: Record<string, boolean>; effectivePermissions?: Record<string, boolean> }>("/auth_me.php");
      setUser(me.user);
      const mePerms = me.effectivePermissions ?? me.permissions;
      if (import.meta.env?.DEV) console.info('[auth] verifyOtp: /auth_me.php follow-up', { user: me.user?.username, role: me.user?.role, mePermsCount: mePerms ? Object.keys(mePerms).length : 0 });
      await applyPermsForUser(me.user, { fallbackPerms: mePerms });
    } catch {
      await applyPermsForUser(r.user);
    }
    // Auto clock-in (silencieux)
    api("/attendance.php?action=clock_in", { method: "POST", body: {} }).catch(() => {});
  };

  const resendOtp = async (challenge: string) => {
    return api<{ expiresAt: string; codeLength?: number }>("/auth_otp_resend.php", {
      method: "POST",
      body: { challenge },
    });
  };

  const signup = async (input: SignupInput) => {
    if (!API_ENABLED) {
      const u: AuthUser = {
        id: "U-DEMO", username: input.username, fullName: input.fullName,
        email: input.email, role: "Agent",
        team: input.team || "Lead-Actifs", active: true,
      };
      setUser(u);
      await applyPermsForUser(u);
      return;
    }
    const r = await api<{ token: string; user: AuthUser }>("/auth_signup.php", {
      method: "POST",
      body: input,
    });
    setToken(r.token);
    setUser(r.user);
    await applyPermsForUser(r.user);
  };

  const changePassword = async (currentPassword: string, newPassword: string) => {
    if (!newPassword || newPassword.length < 8) {
      throw new Error("Le nouveau mot de passe doit contenir au moins 8 caractères.");
    }
    if (currentPassword === newPassword) {
      throw new Error("Le nouveau mot de passe doit être différent de l'actuel.");
    }
    if (!API_ENABLED) {
      // Demo mode: no real backend, just simulate success.
      return;
    }
    await api("/auth_change_password.php", {
      method: "POST",
      body: { currentPassword, newPassword },
    });
    setUser((u) => (u ? { ...u, mustChangePassword: false } : u));
  };

  const updateProfile = async (patch: Partial<AuthUser>): Promise<AuthUser> => {
    if (!API_ENABLED) {
      const next = { ...(user as AuthUser), ...patch };
      setUser(next);
      return next;
    }
    const r = await api<{ user: AuthUser }>("/auth_update_profile.php", {
      method: "POST",
      body: patch,
    });
    setUser((u) => (u ? { ...u, ...r.user } : r.user));
    return r.user;
  };

  const clearMustChange = () => setUser((u) => (u ? { ...u, mustChangePassword: false } : u));

  const logout = async () => {
    if (API_ENABLED) {
      // Prefer navigator.sendBeacon for reliable delivery during unload/redirect.
      // sendBeacon doesn't allow headers so we use authenticated URL with token in query.
      try {
        const beaconSupported = typeof navigator !== "undefined" && typeof navigator.sendBeacon === "function";
        if (beaconSupported) {
          try {
            const attUrl = authenticatedApiUrl("/attendance.php", { action: "clock_out" });
            const authUrl = authenticatedApiUrl("/auth_logout.php");
            // send empty body; backend treats POST without body as valid for these endpoints
            navigator.sendBeacon(attUrl, "");
            navigator.sendBeacon(authUrl, "");
          } catch (e) {
            // fall back to fetch below
            await Promise.allSettled([
              api("/attendance.php?action=clock_out", { method: "POST", body: {}, keepalive: true }),
              api("/auth_logout.php", { method: "POST", keepalive: true }),
            ]);
          }
        } else {
          // No sendBeacon support — keep existing fetch+keepalive method.
          await Promise.allSettled([
            api("/attendance.php?action=clock_out", { method: "POST", body: {}, keepalive: true }),
            api("/auth_logout.php", { method: "POST", keepalive: true }),
          ]);
        }
      } catch (e) {
        // Ensure logout proceeds even if the network calls fail.
        try {
          await Promise.allSettled([
            api("/attendance.php?action=clock_out", { method: "POST", body: {}, keepalive: true }),
            api("/auth_logout.php", { method: "POST", keepalive: true }),
          ]);
        } catch { /* ignore */ }
      }
    }
    // Wipe the per-user permissions cache so the next user on this machine
    // doesn't inherit a stale snapshot.
    if (typeof window !== "undefined") {
      try {
        for (let i = localStorage.length - 1; i >= 0; i--) {
          const k = localStorage.key(i);
          if (k && k.startsWith(PERMS_CACHE_PREFIX)) localStorage.removeItem(k);
        }
      } catch { /* ignore */ }
    }
    setToken(null);
    setUser(null);
    setPermissions({});
    if (typeof window !== "undefined") window.location.href = "/login";
  };



  const hasPermission = useCallback(
    (key: string) => {
      if (!user) return false;
      if (user.role === "Administrateur") return true;
      // If permissions are not yet hydrated, try best-effort using the
      // properties already available on the `user` object (returned by
      // `/auth_me.php`) so the UI doesn't incorrectly hide actions during
      // the short hydration window after login.
      if (!permissionsHydrated) {
        if (Array.isArray(user.grantedPermissions) && user.grantedPermissions.includes(key)) {
          return true;
        }
        if (Array.isArray(user.allowedPermissions) && user.allowedPermissions.includes(key)) {
          return true;
        }
        // DeniedPermissions should override grants when present on the user.
        if (Array.isArray(user.deniedPermissions) && user.deniedPermissions.includes(key)) {
          return false;
        }
      }
      return !!permissions[key];
    },
    [user, permissions, permissionsHydrated],
  );

  const refreshPermissions = useCallback(async () => {
    await syncSession({ silent: false });
  }, [syncSession]);

  return (
    <AuthContext.Provider
      value={{
        user, loading, permissionsLoading, permissionsHydrated, apiEnabled: API_ENABLED,
        permissions, hasPermission, refreshPermissions,
        login, verifyOtp, resendOtp, signup, changePassword, updateProfile, clearMustChange, logout,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) throw new Error("useAuth must be used within AuthProvider");
  return ctx;
}
