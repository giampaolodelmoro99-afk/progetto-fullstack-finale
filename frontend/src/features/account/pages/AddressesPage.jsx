import Add from '@mui/icons-material/Add';
import PlaceOutlined from '@mui/icons-material/PlaceOutlined';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import Grid from '@mui/material/Grid';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import { useState } from 'react';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AccountPageHeader } from '@/features/account/components/AccountPageHeader';
import { AddressCard } from '@/features/account/components/AddressCard';
import { AddressDialog } from '@/features/account/components/AddressDialog';
import { DeleteAddressDialog } from '@/features/account/components/DeleteAddressDialog';
import { useAddresses } from '@/features/account/hooks/useAddresses';
import { useUpdateAddress } from '@/features/account/hooks/useUpdateAddress';
import { useNotify } from '@/hooks/useNotify';

function AddressesSkeleton() {
  return (
    <Grid container spacing={2} role="status" aria-label="Caricamento degli indirizzi">
      {[0, 1].map((index) => (
        <Grid key={index} size={{ xs: 12, md: 6 }}>
          <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(23) }} />
        </Grid>
      ))}
    </Grid>
  );
}

function AddButton({ onClick }) {
  return (
    <Button
      variant="outlined"
      startIcon={<Add />}
      onClick={onClick}
      sx={{ width: { xs: 1, sm: 'auto' } }}
    >
      Aggiungi un indirizzo
    </Button>
  );
}

export default function AddressesPage() {
  const addressesQuery = useAddresses();
  const setDefault = useUpdateAddress();
  const notify = useNotify();
  const [editing, setEditing] = useState(null);
  const [deleting, setDeleting] = useState(null);
  const addresses = addressesQuery.data ?? [];

  function openNew() {
    setEditing({ address: null });
  }

  function handleSaved() {
    notify({ message: editing.address ? 'Indirizzo aggiornato' : 'Indirizzo salvato' });
    setEditing(null);
  }

  function makeDefault(address) {
    setDefault.mutate(
      { id: address.id, is_default: true },
      {
        onSuccess: () => notify({ message: 'Indirizzo predefinito aggiornato' }),
        onError: (error) => notify({ message: error.message, severity: 'error' }),
      },
    );
  }

  return (
    <Stack sx={{ gap: 3 }}>
      <AccountPageHeader
        title="Indirizzi"
        description="Gli indirizzi salvati ti vengono proposti al checkout."
      />
      {addressesQuery.isPending ? <AddressesSkeleton /> : null}
      {addressesQuery.isError ? (
        <ErrorState
          title="Non è stato possibile caricare i tuoi indirizzi"
          message="Controlla la connessione e riprova."
          onRetry={() => addressesQuery.refetch()}
        />
      ) : null}
      {addressesQuery.isSuccess && addresses.length === 0 ? (
        <Card>
          <EmptyState
            icon={<PlaceOutlined />}
            title="Non hai ancora salvato indirizzi"
            titleComponent="h3"
            description="Aggiungine uno: te lo proporremo al checkout."
            action={
              <Button variant="contained" startIcon={<Add />} onClick={openNew}>
                Aggiungi un indirizzo
              </Button>
            }
          />
        </Card>
      ) : null}
      {addresses.length > 0 ? (
        <>
          <Grid container spacing={2}>
            {addresses.map((address) => (
              <Grid key={address.id} size={{ xs: 12, md: 6 }}>
                <AddressCard
                  address={address}
                  onEdit={(selected) => setEditing({ address: selected })}
                  onDelete={setDeleting}
                  onMakeDefault={makeDefault}
                  makingDefault={setDefault.isPending && setDefault.variables?.id === address.id}
                />
              </Grid>
            ))}
          </Grid>
          <Box>
            <AddButton onClick={openNew} />
          </Box>
        </>
      ) : null}
      {editing ? (
        <AddressDialog
          address={editing.address}
          onClose={() => setEditing(null)}
          onSaved={handleSaved}
        />
      ) : null}
      {deleting ? (
        <DeleteAddressDialog address={deleting} onClose={() => setDeleting(null)} />
      ) : null}
    </Stack>
  );
}
