import Stack from '@mui/material/Stack';
import { AccountPageHeader } from '@/features/account/components/AccountPageHeader';
import { EmailCard } from '@/features/account/components/EmailCard';
import { PersonalDataCard } from '@/features/account/components/PersonalDataCard';
import { useAuth } from '@/features/auth/hooks/useAuth';

export default function ProfilePage() {
  const { user } = useAuth();

  return (
    <Stack sx={{ gap: 3 }}>
      <AccountPageHeader
        title="Profilo"
        description="I dati che usiamo per i tuoi ordini e per contattarti."
      />
      <PersonalDataCard key={user.id} user={user} />
      <EmailCard user={user} />
    </Stack>
  );
}
