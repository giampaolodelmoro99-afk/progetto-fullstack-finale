import Divider from '@mui/material/Divider';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import { ErrorState } from '@/components/feedback/ErrorState';
import { AccountPageHeader } from '@/features/account/components/AccountPageHeader';
import { useAuth } from '@/features/auth/hooks/useAuth';
import { DeleteAccountSection } from '@/features/security/components/DeleteAccountSection';
import { PasskeysCard } from '@/features/security/components/PasskeysCard';
import { PasswordCard } from '@/features/security/components/PasswordCard';
import { RecentActivityCard } from '@/features/security/components/RecentActivityCard';
import { TwoFactorCard } from '@/features/security/components/TwoFactorCard';
import { useSecurityOverview } from '@/features/security/hooks/useSecurityOverview';

function OverviewSkeleton() {
  return (
    <Stack sx={{ gap: 3 }} role="status" aria-label="Caricamento dei dati di sicurezza">
      <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(14) }} />
      <Skeleton variant="rounded" sx={{ height: (theme) => theme.spacing(24) }} />
    </Stack>
  );
}

export default function SecurityPage() {
  const { isAdmin } = useAuth();
  const overview = useSecurityOverview();

  return (
    <Stack sx={{ gap: 3 }}>
      <AccountPageHeader
        title="Sicurezza"
        description="Gestisci la password, la doppia autenticazione e i dispositivi con cui accedi."
      />
      {overview.isPending ? <OverviewSkeleton /> : null}
      {overview.isError ? (
        <ErrorState
          message="Non è stato possibile caricare i dati di sicurezza del tuo account."
          onRetry={() => overview.refetch()}
        />
      ) : null}
      {overview.data ? (
        <>
          <PasswordCard overview={overview.data} />
          <TwoFactorCard overview={overview.data} />
          <PasskeysCard twoFactorEnabled={overview.data.two_factor_enabled} />
        </>
      ) : null}
      <RecentActivityCard />
      {isAdmin ? null : (
        <>
          <Divider />
          <DeleteAccountSection />
        </>
      )}
    </Stack>
  );
}
