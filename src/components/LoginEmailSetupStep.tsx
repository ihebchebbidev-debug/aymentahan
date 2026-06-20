import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { AlertCircle, ArrowLeft, Loader2, Mail } from "lucide-react";

export function LoginEmailSetupStep({
  currentEmail,
  message,
  submitting,
  error,
  onBack,
  onSubmit,
}: {
  currentEmail: string;
  message: string;
  submitting: boolean;
  error: string | null;
  onBack: () => void;
  onSubmit: (newEmail: string) => void;
}) {
  const [email, setEmail] = useState("");

  return (
    <div className="space-y-5">
      <div className="space-y-1.5 text-center">
        <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
          <Mail className="h-6 w-6 text-primary" />
        </div>
        <h1 className="text-[22px] font-semibold tracking-tight">Configurer votre email</h1>
        <p className="text-sm text-muted-foreground">{message}</p>
        <p className="text-xs text-muted-foreground">
          Adresse actuelle : <span className="font-medium">{currentEmail}</span>
        </p>
      </div>

      <form
        className="space-y-4"
        onSubmit={(e) => {
          e.preventDefault();
          onSubmit(email.trim());
        }}
      >
        <div className="space-y-1.5">
          <Label htmlFor="new-email" className="text-xs font-medium text-muted-foreground">
            Nouvelle adresse email
          </Label>
          <Input
            id="new-email"
            type="email"
            autoComplete="email"
            autoFocus
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="vous@exemple.com"
            disabled={submitting}
            maxLength={160}
            className="h-11"
            required
          />
        </div>

        {error && (
          <div className="rounded-lg border border-destructive/30 bg-destructive/10 px-3 py-2.5 text-sm text-destructive flex items-start gap-2">
            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
            {error}
          </div>
        )}

        <Button type="submit" size="lg" className="w-full h-11" disabled={submitting || !email.trim()}>
          {submitting ? (
            <>
              <Loader2 className="h-4 w-4 mr-2 animate-spin" />
              Envoi du code…
            </>
          ) : (
            "Enregistrer et recevoir le code"
          )}
        </Button>

        <Button type="button" variant="ghost" className="w-full" onClick={onBack} disabled={submitting}>
          <ArrowLeft className="h-4 w-4 mr-2" />
          Retour
        </Button>
      </form>
    </div>
  );
}
