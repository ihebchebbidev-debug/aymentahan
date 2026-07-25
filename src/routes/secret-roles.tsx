import { createFileRoute } from "@tanstack/react-router";
import { useEffect, useState } from "react";
import { ShieldAlert, Users, KeyRound, Search } from "lucide-react";
import { Card } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Badge } from "@/components/ui/badge";
import { API_BASE } from "@/lib/api";

export const Route = createFileRoute("/secret-roles")({
  component: SecretRolesPage,
});

function SecretRolesPage() {
  const [users, setUsers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState("");

  useEffect(() => {
    fetch(`${API_BASE}/secret_roles.php?_t=${Date.now()}`)
      .then(res => res.json())
      .then(data => {
        setUsers(data.users || []);
        setLoading(false);
      })
      .catch(err => {
        console.error(err);
        setLoading(false);
      });
  }, []);

  const filtered = users.filter(u => 
    u.username.toLowerCase().includes(search.toLowerCase()) || 
    (u.full_name && u.full_name.toLowerCase().includes(search.toLowerCase())) ||
    (u.role && u.role.toLowerCase().includes(search.toLowerCase()))
  );

  return (
    <div className="min-h-screen bg-slate-950 text-slate-200 p-8 font-sans selection:bg-purple-500/30">
      <div className="max-w-5xl mx-auto space-y-8">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-purple-400 to-pink-600 flex items-center gap-3">
              <ShieldAlert className="h-8 w-8 text-purple-500" />
              CONFIDENTIAL: ROLE MATRIX
            </h1>
            <p className="text-slate-400 mt-2">Bypassing auth gates. Read-only user roles.</p>
          </div>
          
          <div className="relative w-72">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-500" />
            <Input 
              className="pl-9 bg-slate-900 border-slate-800 text-slate-200 focus-visible:ring-purple-500" 
              placeholder="Search by username, role..." 
              value={search}
              onChange={e => setSearch(e.target.value)}
            />
          </div>
        </div>

        {loading ? (
          <div className="flex items-center justify-center py-20 text-slate-500">
            <div className="animate-pulse flex items-center gap-2">
              <KeyRound className="h-5 w-5 animate-spin" /> Fetching secure payload...
            </div>
          </div>
        ) : (
          <Card className="bg-slate-900 border-slate-800 overflow-hidden shadow-2xl shadow-purple-900/10">
            <table className="w-full text-left text-sm whitespace-nowrap">
              <thead className="bg-slate-950/50 border-b border-slate-800 text-slate-400">
                <tr>
                  <th className="px-6 py-4 font-semibold">User</th>
                  <th className="px-6 py-4 font-semibold">Email</th>
                  <th className="px-6 py-4 font-semibold">Role Assigned</th>
                  <th className="px-6 py-4 font-semibold text-right">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-800/50">
                {filtered.map(u => (
                  <tr key={u.id} className="hover:bg-slate-800/30 transition-colors">
                    <td className="px-6 py-4">
                      <div className="flex items-center gap-3">
                        <div className="h-8 w-8 rounded-full bg-gradient-to-tr from-purple-600 to-pink-500 flex items-center justify-center text-xs font-bold text-white shadow-lg">
                          {u.full_name?.substring(0,2).toUpperCase() || '??'}
                        </div>
                        <div>
                          <div className="font-bold text-slate-200">{u.username}</div>
                          <div className="text-xs text-slate-500">{u.full_name}</div>
                        </div>
                      </div>
                    </td>
                    <td className="px-6 py-4 text-slate-400">{u.email}</td>
                    <td className="px-6 py-4">
                      <Badge className={`
                        ${u.role === 'Administrateur' ? 'bg-red-500/10 text-red-400 hover:bg-red-500/20' : ''}
                        ${u.role === 'Agent' ? 'bg-blue-500/10 text-blue-400 hover:bg-blue-500/20' : ''}
                        ${u.role === 'Manager' ? 'bg-green-500/10 text-green-400 hover:bg-green-500/20' : ''}
                        ${!['Administrateur', 'Agent', 'Manager'].includes(u.role) ? 'bg-purple-500/10 text-purple-400 hover:bg-purple-500/20' : ''}
                        border-0
                      `}>
                        {u.role || 'NONE'}
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
                  </tr>
                ))}
                {filtered.length === 0 && (
                  <tr>
                    <td colSpan={4} className="px-6 py-12 text-center text-slate-500">
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
