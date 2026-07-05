import { useEffect } from "react";

/** Module-level counter — any open form with unsaved edits increments this. */
let unsavedFormCount = 0;

export function hasUnsavedForms(): boolean {
  return unsavedFormCount > 0;
}

export function registerUnsavedForm(): () => void {
  unsavedFormCount += 1;
  return () => {
    unsavedFormCount = Math.max(0, unsavedFormCount - 1);
  };
}

/** Call while the user has typed but not yet saved/submitted. */
export function useUnsavedForm(isDirty: boolean): void {
  useEffect(() => {
    if (!isDirty) return;
    return registerUnsavedForm();
  }, [isDirty]);
}
