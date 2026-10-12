import { ConfirmDialog } from '@/features/admin/components/ConfirmDialog';

export function UnsavedChangesDialog({ onStay, onLeave }) {
  return (
    <ConfirmDialog
      title="Uscire senza salvare?"
      description="Le modifiche che non hai salvato andranno perse."
      cancelLabel="Resta sulla pagina"
      confirmLabel="Esci senza salvare"
      confirmColor="primary"
      onConfirm={onLeave}
      onClose={onStay}
    />
  );
}
