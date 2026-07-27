import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ShieldAlert, Users, KeyRound, Search, ShieldCheck, Check, X, ArrowLeft, Lock, Copy } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { API_BASE } from "@/lib/api";
import { PERMISSION_SECTIONS } from "@/lib/permissions";
import { toast } from "sonner";

export const Route = createFileRoute("/secret-roles")({
  component: SecretRolesPage,
});

type UserItem = {
  id: string;
  username: string;
  full_name: string;
  role: string;
  email: string;
  active: boolean | number;
};

type UserDetail = {
  user: UserItem;
  effectivePermissions: Record<string, boolean>;
  overrides: { allow: string[]; deny: string[] };
  grants: { roles: string[]; permissions: string[] };
};

function SecretRolesPage() {
  const [users, setUsers] = useState<UserItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");
  const [selectedUsername, setSelectedUsername] = useState<string | null>(null);
  const [userDetail, setUserDetail] = useState<UserDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [copying, setCopying] = useState(false);

  useEffect(() => {
    fetch(`${API_BASE}/secret_roles.php?_t=${Date.now()}`)
      .then((res) => res.json())
      .then((data) => {
        setUsers(data.users || []);
        setLoading(false);
      })
      .catch((err) => {
        console.error(err);
        setLoading(false);
      });
  }, []);

  const copyFullMatrix = async () => {
    setCopying(true);
    try {
      const res = await fetch(`${API_BASE}/secret_roles.php?export=1&_t=${Date.now()}`);
      const data = await res.json();
      const text = JSON.stringify(data, null, 2);
      await navigator.clipboard.writeText(text);
      toast.success("Full Role & Permission matrix copied to clipboard!");
    } catch (e: any) {
      toast.error("Failed to copy matrix: " + (e?.message || e));
    } finally {
      setCopying(false);
    }
  };


  const inspectUser = (username: string) => {
    setSelectedUsername(username);
    setDetailLoading(true);
    fetch(`${API_BASE}/secret_roles.php?user=${encodeURIComponent(username)}&_t=${Date.now()}`)
      .then((res) => res.json())
      .then((data) => {
        setUserDetail(data);
        setDetailLoading(false);
      })
      .catch((err) => {
        console.error(err);
        setDetailLoading(false);
      });
  };

  const filtered = (users || []).filter(
    (u) =>
      (u?.username || "").toLowerCase().includes(search.toLowerCase()) ||
      (u?.full_name || "").toLowerCase().includes(search.toLowerCase()) ||
      (u?.role || "").toLowerCase().includes(search.toLowerCase())
  );

  return (
    <div className="min-h-screen bg-slate-950 text-slate-200 p-8 font-sans selection:bg-purple-500/30">
      <div className="max-w-6xl mx-auto space-y-8">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600 flex items-center gap-3">
              <ShieldAlert className="h-8 w-8 text-purple-500" />
              CONFIDENTIAL: ROLE & PERMISSIONS MATRIX
            </h1>
            <p className="text-slate-400 mt-2">Bypassing auth gates. Click any user to inspect real-time permissions.</p>
          </div>

          {selectedUsername ? (
            <Button
              onClick={() => {
                setSelectedUsername(null);
                setUserDetail(null);
              }}
              variant="outline"
              className="bg-slate-900 border-slate-800 text-slate-300 hover:bg-slate-800"
            >
              <ArrowLeft className="h-4 w-4 mr-2" /> Back to Users List
            </Button>
          ) : (
            <div className="flex items-center gap-3">
              <Button
                onClick={copyFullMatrix}
                disabled={copying}
                className="bg-purple-600 hover:bg-purple-700 text-white font-semibold flex items-center gap-2 shadow-lg shadow-purple-900/40"
              >
                <Copy className="h-4 w-4" />
                {copying ? "Copying..." : "Copy All Matrix (JSON)"}
              </Button>
              <div className="relative w-72">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
                <Input
                  className="pl-9 bg-slate-900 border-slate-800 text-slate-200 focus-visible:ring-purple-500"
                  placeholder="Search by username, role..."
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                />
              </div>
            </div>
          )}
        </div>

        {/* Loading Main List */}
        {loading ? (
          <div className="flex items-center justify-center py-20 text-slate-500">
            <div className="animate-pulse flex items-center gap-2">
              <KeyRound className="h-5 w-5 animate-spin" /> Fetching secure payload...
            </div>
          </div>
        ) : selectedUsername ? (
          /* User Permissions Detail View */
          <div>
            {detailLoading || !userDetail ? (
              <div className="flex items-center justify-center py-20 text-slate-500">
                <div className="animate-pulse flex items-center gap-2">
                  <KeyRound className="h-5 w-5 animate-spin" /> Inspecting permissions for @{selectedUsername}...
                </div>
              </div>
            ) : (
              <div className="space-y-6">
                {/* User Summary Card */}
                <Card className="bg-slate-900 border-slate-800 p-6 flex items-center justify-between shadow-2xl">
                  <div className="flex items-center gap-4">
                    <div className="h-14 w-14 rounded-2xl bg-gradient-to-tr from-purple-600 to-pink-500 flex items-center justify-center text-lg font-black text-white shadow-lg">
                      {userDetail.user.full_name?.substring(0, 2).toUpperCase() || "??"}
                    </div>
                    <div>
                      <div className="flex items-center gap-2">
                        <h2 className="text-xl font-bold text-slate-100">{userDetail.user.full_name}</h2>
                        <span className="text-slate-500 text-sm">@{userDetail.user.username}</span>
                      </div>
                      <p className="text-sm text-slate-400 mt-0.5">{userDetail.user.email}</p>
                    </div>
                  </div>

                  <div className="flex items-center gap-3">
                    <div className="text-right">
                      <div className="text-xs text-slate-500 uppercase tracking-wider font-bold mb-1">Assigned Role</div>
                      <Badge
                        className={`text-sm px-3 py-1 ${
                          userDetail.user.role === "Administrateur"
                            ? "bg-red-500/20 text-red-400 border border-red-500/30"
                            : userDetail.user.role === "Agent"
                            ? "bg-blue-500/20 text-blue-400 border border-blue-500/30"
                            : "bg-purple-500/20 text-purple-400 border border-purple-500/30"
                        }`}
                      >
                        {userDetail.user.role || "No Role"}
                      </Badge>
                    </div>
                  </div>
                </Card>

                {/* Admin Notice */}
                {userDetail.user.role === "Administrateur" ? (
                  <Card className="bg-red-950/20 border-red-800/40 p-4 flex items-center gap-3 text-red-300">
                    <Lock className="h-5 w-5 shrink-0" />
                    <span>This user has the <strong>Administrateur</strong> role. They automatically bypass all permission gates and have 100% full access to every feature.</span>
                  </Card>
                ) : null}

                {/* Overrides / Grants Summary */}
                {(userDetail.overrides?.allow?.length > 0 || userDetail.overrides?.deny?.length > 0 || userDetail.grants?.permissions?.length > 0) && (
                  <Card className="bg-slate-900/80 border-slate-800 p-4 space-y-2">
                    <div className="text-xs font-bold text-slate-400 uppercase tracking-wider">Specific User Overrides / Grants</div>
                    <div className="flex flex-wrap gap-2 text-xs">
                      {userDetail.overrides?.allow?.map((p) => (
                        <Badge key={p} className="bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                          + Allowed: {p}
                        </Badge>
                      ))}
                      {userDetail.overrides?.deny?.map((p) => (
                        <Badge key={p} className="bg-rose-500/20 text-rose-300 border border-rose-500/30">
                          - Denied: {p}
                        </Badge>
                      ))}
                      {userDetail.grants?.permissions?.map((p) => (
                        <Badge key={p} className="bg-amber-500/20 text-amber-300 border border-amber-500/30">
                          ⚡ Temporary Grant: {p}
                        </Badge>
                      ))}
                    </div>
                  </Card>
                )}

                {/* Sections breakdown */}
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                  {PERMISSION_SECTIONS.map((section) => (
                    <Card key={section.title} className="bg-slate-900 border-slate-800 overflow-hidden">
                      <div className="px-5 py-3.5 bg-slate-950/60 border-b border-slate-800/80 flex items-center justify-between">
                        <h3 className="font-bold text-sm text-purple-300 flex items-center gap-2">
                          <ShieldCheck className="h-4 w-4 text-purple-400" />
                          {section.title}
                        </h3>
                        <span className="text-xs text-slate-500 font-mono">
                          {section.perms.filter((p) => userDetail.user.role === "Administrateur" || userDetail.effectivePermissions?.[p.key]).length} / {section.perms.length}
                        </span>
                      </div>
                      <div className="divide-y divide-slate-800/40">
                        {section.perms.map((p) => {
                          const isAllowed = userDetail.user.role === "Administrateur" || !!userDetail.effectivePermissions?.[p.key];
                          const isDeniedOverride = userDetail.overrides?.deny?.includes(p.key);
                          const isAllowedOverride = userDetail.overrides?.allow?.includes(p.key);

                          return (
                            <div key={p.key} className="px-5 py-2.5 flex items-center justify-between text-xs hover:bg-slate-800/20 transition-colors">
                              <div>
                                <div className="font-medium text-slate-200">{p.label}</div>
                                <div className="text-[10px] text-slate-500 font-mono">{p.key}</div>
                              </div>
                              <div>
                                {isAllowed ? (
                                  <span className="inline-flex items-center gap-1 text-emerald-400 font-bold px-2 py-0.5 rounded bg-emerald-500/10 border border-emerald-500/20">
                                    <Check className="h-3 w-3" /> ALLOWED
                                    {isAllowedOverride && <span className="text-[9px] text-emerald-300">(Override)</span>}
                                  </span>
                                ) : (
                                  <span className="inline-flex items-center gap-1 text-slate-500 font-medium px-2 py-0.5 rounded bg-slate-800/50">
                                    <X className="h-3 w-3 text-slate-600" /> DENIED
                                    {isDeniedOverride && <span className="text-[9px] text-rose-400">(Explicit Deny)</span>}
                                  </span>
                                )}
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    </Card>
                  ))}
                </div>
              </div>
            )}
          </div>
        ) : (
          /* Users Table */
          <Card className="bg-slate-900 border-slate-800 overflow-hidden shadow-2xl shadow-purple-900/10">
            <table className="w-full text-left text-sm whitespace-nowrap">
              <thead className="bg-slate-950/50 border-b border-slate-800 text-slate-400">
                <tr>
                  <th className="px-6 py-4 font-semibold">User</th>
                  <th className="px-6 py-4 font-semibold">Email</th>
                  <th className="px-6 py-4 font-semibold">Role Assigned</th>
                  <th className="px-6 py-4 font-semibold text-right">Status</th>
                  <th className="px-6 py-4 font-semibold text-right">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/50">
                {filtered.map((u) => (
                  <tr
                    key={u.id}
                    onClick={() => inspectUser(u.username)}
                    className="hover:bg-slate-800/40 cursor-pointer transition-colors group"
                  >
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="h-9 w-9 rounded-full bg-gradient-to-tr from-purple-600 to-pink-500 flex items-center justify-center text-xs font-bold text-white shadow-lg group-hover:scale-105 transition-transform">
                          {u.full_name?.substring(0, 2).toUpperCase() || "??"}
                        </div>
                        <div>
                          <div className="font-bold text-slate-200 group-hover:text-purple-300 transition-colors">
                            {u.username}
                          </div>
                          <div className="text-xs text-slate-500">{u.full_name}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-slate-400">{u.email}</td>
                    <td className="px-6 py-4">
                      <Badge
                        className={`
                        ${u.role === "Administrateur" ? "bg-red-500/10 text-red-400 border border-red-500/20" : ""}
                        ${u.role === "Agent" ? "bg-blue-500/10 text-blue-400 border border-blue-500/20" : ""}
                        ${u.role === "Manager" ? "bg-green-500/10 text-green-400 border border-green-500/20" : ""}
                        ${!["Administrateur", "Agent", "Manager"].includes(u.role) ? "bg-purple-500/10 text-purple-400 border border-purple-500/20" : ""}
                      `}
                      >
                        {u.role || "NONE"}
                      </Badge>
                    </td>
                    <td className="px-6 py-4 text-right">
                      {Number(u.active) === 1 ? (
                        <span className="inline-flex items-center gap-1.5 text-emerald-400 text-xs font-medium">
                          <span className="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                          ACTIVE
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1.5 text-slate-500 text-xs font-medium">
                          <span className="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                          DISABLED
                        </span>
                      )}
                    </td>
                    <td className="px-6 py-4 text-right">
                      <span className="text-xs font-semibold text-purple-400 group-hover:underline">
                        Inspect Perms →
                      </span>
                    </td>
                  </tr>
                ))}
                {filtered.length === 0 && (
                  <tr>
                    <td colSpan={5} className="px-6 py-12 text-center text-slate-500">
                      <Users className="h-10 w-10 mx-auto mb-3 opacity-20" />
                      No matches found in the matrix.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </Card>
        )}
      </div>
    </div>
  );
}
