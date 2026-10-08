import Add from '@mui/icons-material/Add';
import Alert from '@mui/material/Alert';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Card from '@mui/material/Card';
import CardContent from '@mui/material/CardContent';
import Chip from '@mui/material/Chip';
import Radio from '@mui/material/Radio';
import RadioGroup from '@mui/material/RadioGroup';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AddressForm } from '@/features/account/components/AddressForm';
import { useAddresses } from '@/features/account/hooks/useAddresses';
import { formatPostalLine } from '@/utils/format';

export const ADDRESS_FORM_ID = 'checkout-address-form';

function AddressOption({ address, selected }) {
  const nameId = `address-${address.id}-name`;
  const streetId = `address-${address.id}-street`;

  return (
    <Box
      component="label"
      sx={{
        display: 'flex',
        gap: 1.5,
        alignItems: 'flex-start',
        p: 2,
        borderRadius: 2,
        border: selected ? 2 : 1,
        borderColor: selected ? 'primary.main' : 'divider',
        cursor: 'pointer',
        bgcolor: 'background.paper',
      }}
    >
      <Radio
        value={String(address.id)}
        slotProps={{ input: { 'aria-labelledby': `${nameId} ${streetId}` } }}
      />
      <Stack sx={{ gap: 0.25, pt: 1 }}>
        <Stack direction="row" sx={{ gap: 1, alignItems: 'center', flexWrap: 'wrap' }}>
          <Typography id={nameId} variant="subtitle2" component="span">
            {address.full_name}
          </Typography>
          {address.is_default ? <Chip label="Predefinito" size="small" /> : null}
        </Stack>
        <Typography id={streetId} variant="body2" sx={{ color: 'text.secondary' }}>
          {address.street}
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {formatPostalLine(address)}
        </Typography>
        <Typography variant="body2" sx={{ color: 'text.secondary' }}>
          {address.phone}
        </Typography>
      </Stack>
    </Box>
  );
}

export function AddressStep({ addressId, error, primaryInline, onConfirm }) {
  const addressesQuery = useAddresses();
  const addresses = addressesQuery.data ?? [];
  const [selected, setSelected] = useState(addressId);
  const [adding, setAdding] = useState(false);
  const [missing, setMissing] = useState(false);
  const fallbackId = addresses.find((address) => address.is_default)?.id ?? addresses[0]?.id;
  const current = addresses.some((address) => address.id === selected) ? selected : fallbackId;
  const showForm = adding || (addressesQuery.isSuccess && addresses.length === 0);

  function handleSubmit(event) {
    event.preventDefault();

    if (current === undefined || current === null) {
      setMissing(true);

      return;
    }

    onConfirm(current);
  }

  function handleSaved(address) {
    setSelected(address.id);
    setAdding(false);
    setMissing(false);
  }

  return (
    <Card component="section" aria-labelledby="address-step-title">
      <CardContent>
        <Stack sx={{ gap: 3 }}>
          <Typography id="address-step-title" variant="h4" component="h2" tabIndex={-1}>
            Indirizzo di spedizione
          </Typography>
          {error ? <Alert severity="error">{error}</Alert> : null}
          {addressesQuery.isPending ? (
            <Stack sx={{ gap: 2 }} role="status" aria-label="Caricamento degli indirizzi">
              <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(12) }} />
              <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(12) }} />
            </Stack>
          ) : null}
          {addressesQuery.isError ? (
            <ErrorState
              title="Non è stato possibile caricare i tuoi indirizzi"
              message="Controlla la connessione e riprova."
              onRetry={() => addressesQuery.refetch()}
            />
          ) : null}
          <Stack
            component="form"
            id={ADDRESS_FORM_ID}
            noValidate
            onSubmit={handleSubmit}
            sx={{ gap: 2 }}
          >
            {addresses.length > 0 ? (
              <RadioGroup
                aria-label="Indirizzi salvati"
                value={current === undefined ? '' : String(current)}
                onChange={(event) => {
                  setSelected(Number(event.target.value));
                  setMissing(false);
                }}
                sx={{ gap: 1.5 }}
              >
                {addresses.map((address) => (
                  <AddressOption
                    key={address.id}
                    address={address}
                    selected={address.id === current}
                  />
                ))}
              </RadioGroup>
            ) : null}
            {missing ? <Alert severity="error">Scegli un indirizzo di spedizione.</Alert> : null}
          </Stack>
          {showForm ? (
            <AddressForm
              onSaved={handleSaved}
              onCancel={addresses.length > 0 ? () => setAdding(false) : undefined}
            />
          ) : null}
          {!showForm && addressesQuery.isSuccess ? (
            <Box>
              <Button variant="outlined" startIcon={<Add />} onClick={() => setAdding(true)}>
                Aggiungi un indirizzo
              </Button>
            </Box>
          ) : null}
          {primaryInline ? (
            <Stack direction="row" sx={{ justifyContent: 'flex-end' }}>
              <Button type="submit" form={ADDRESS_FORM_ID} variant="contained" size="large">
                Continua al pagamento
              </Button>
            </Stack>
          ) : null}
        </Stack>
      </CardContent>
    </Card>
  );
}
