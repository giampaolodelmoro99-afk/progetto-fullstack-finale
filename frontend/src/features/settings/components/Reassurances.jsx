import LocalShippingOutlined from '@mui/icons-material/LocalShippingOutlined';
import ReplayOutlined from '@mui/icons-material/ReplayOutlined';
import VerifiedUserOutlined from '@mui/icons-material/VerifiedUserOutlined';
import Avatar from '@mui/material/Avatar';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { InlineAmountSkeleton } from '@/features/settings/components/InlineAmountSkeleton';
import { useShopSettings } from '@/features/settings/hooks/useShopSettings';
import { formatPrice } from '@/utils/format';

function threshold(settingsQuery) {
  return settingsQuery.isPending ? (
    <InlineAmountSkeleton />
  ) : (
    formatPrice(settingsQuery.data.free_shipping_threshold)
  );
}

function homeItems(settingsQuery) {
  return [
    settingsQuery.isError
      ? null
      : {
          key: 'shipping',
          icon: LocalShippingOutlined,
          title: <>Spedizione gratuita sopra {threshold(settingsQuery)}</>,
          description: 'Consegna in 24/48 ore in tutta Italia.',
        },
    {
      key: 'returns',
      icon: ReplayOutlined,
      title: 'Reso entro 30 giorni',
      description: 'Se non ti convince, lo rimandi indietro.',
    },
    {
      key: 'warranty',
      icon: VerifiedUserOutlined,
      title: 'Garanzia di 2 anni',
      description: 'Su tutti i prodotti della linea.',
    },
  ].filter(Boolean);
}

function compactItems(settingsQuery) {
  return [
    settingsQuery.isError
      ? null
      : {
          key: 'shipping',
          icon: LocalShippingOutlined,
          title: <>Spedizione gratuita sopra {threshold(settingsQuery)}, consegna in 24/48 ore</>,
        },
    { key: 'returns', icon: ReplayOutlined, title: 'Reso entro 30 giorni' },
    { key: 'warranty', icon: VerifiedUserOutlined, title: 'Garanzia di 2 anni' },
  ].filter(Boolean);
}

export function Reassurances({ variant = 'home' }) {
  const settingsQuery = useShopSettings();

  if (variant === 'compact') {
    return (
      <Stack
        component="ul"
        aria-label="Spedizione, resi e garanzia"
        sx={{ listStyle: 'none', m: 0, p: 0, gap: 1.5 }}
      >
        {compactItems(settingsQuery).map((item) => (
          <Stack
            component="li"
            key={item.key}
            direction="row"
            sx={{ alignItems: 'center', gap: 1.5 }}
          >
            <item.icon fontSize="small" aria-hidden="true" />
            <Typography variant="body2" component="span">
              {item.title}
            </Typography>
          </Stack>
        ))}
      </Stack>
    );
  }

  return (
    <Stack
      component="ul"
      aria-label="Spedizione, resi e garanzia"
      direction={{ xs: 'column', md: 'row' }}
      sx={{ listStyle: 'none', m: 0, p: 0, gap: { xs: 3, md: 4 }, justifyContent: 'space-between' }}
    >
      {homeItems(settingsQuery).map((item) => (
        <Stack
          component="li"
          key={item.key}
          direction="row"
          sx={{ alignItems: 'center', gap: 2, flex: 1 }}
        >
          <Avatar sx={{ bgcolor: 'background.paper', color: 'text.primary' }}>
            <item.icon fontSize="small" aria-hidden="true" />
          </Avatar>
          <Stack>
            <Typography variant="subtitle2" component="p">
              {item.title}
            </Typography>
            <Typography variant="body2" sx={{ color: 'text.secondary' }}>
              {item.description}
            </Typography>
          </Stack>
        </Stack>
      ))}
    </Stack>
  );
}
