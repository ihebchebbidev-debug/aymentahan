import { Outlet, Link, createRootRoute } from "@tanstack/react-router";
import { QueryClientProvider } from "@tanstack/react-query";
import { useState, useEffect } from "react";
import { ErpProvider } from "@/lib/erpStore";
import { AuthProvider } from "@/lib/auth";
import { ChatProvider } from "@/lib/chatStore";
import { Toaster } from "@/components/ui/sonner";
import { ConfirmDialogProvider } from "@/components/ConfirmDialogProvider";
import { PermissionDeniedDialogProvider } from "@/components/PermissionDeniedDialog";
import { RouteProgressBar } from "@/components/RouteProgressBar";
import { VersionWatcher } from "@/components/VersionWatcher";
import { createAppQueryClient } from "@/lib/queryClient";
import { bindAppQueryClient } from "@/lib/appQueryClient";
import { setForbiddenHandler } from "@/lib/api";
import { notifyMissingPermission, inferPermissionFromUrl, extractPermissionsFromMessage } from "@/lib/permissionGuard";

function NotFoundComponent() {
  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="max-w-md text-center">
        <h1 className="text-7xl font-bold text-foreground">404</h1>
        <h2 className="mt-4 text-xl font-semibold text-foreground">Page not found</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          The page you're looking for doesn't exist or has been moved.
        </p>
        <div className="mt-6">
          <Link
            to="/"
            className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition-colors hover:bg-primary/90"
          >
            Go home
          </Link>
        </div>
      </div>
    </div>
  );
}

export const Route = createRootRoute({
  component: RootComponent,
  notFoundComponent: NotFoundComponent,
});

function RootComponent() {
  // One QueryClient per browser session (per request on SSR — avoids leaking caches).
  const [queryClient] = useState(() => createAppQueryClient());
  useEffect(() => {
    bindAppQueryClient(queryClient);
  }, [queryClient]);
  // Wire global 403 → French permission toast.
  useEffect(() => {
    setForbiddenHandler(({ url, message }) => {
      // Prefer the exact permission keys the backend named in its 403 message,
      // fall back to a URL-based guess so the dialog still surfaces something
      // concrete when the backend didn't spell it out.
      const fromMsg = extractPermissionsFromMessage(message);
      if (fromMsg.length > 0) {
        notifyMissingPermission(fromMsg[0], { perms: fromMsg });
        return;
      }
      const inferred = inferPermissionFromUrl(url);
      notifyMissingPermission(inferred);
    });
    return () => setForbiddenHandler(null);
  }, []);
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ErpProvider>
          <ChatProvider>
            <ConfirmDialogProvider>
              <PermissionDeniedDialogProvider>
                <RouteProgressBar />
                <VersionWatcher />
                <Outlet />
                <Toaster richColors position="top-right" />
              </PermissionDeniedDialogProvider>
            </ConfirmDialogProvider>
          </ChatProvider>
        </ErpProvider>
      </AuthProvider>
    </QueryClientProvider>
  );
}
