import Divider from '@mui/material/Divider';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import { EmptyState } from '@/components/feedback/EmptyState';
import { ErrorState } from '@/components/feedback/ErrorState';
import { InfoPageLayout } from '@/features/info/components/InfoPageLayout';
import { InfoSection } from '@/features/info/components/InfoSection';
import { useScrollToHash } from '@/features/info/hooks/useScrollToHash';
import { useSupportContent } from '@/features/settings/hooks/useSupportContent';

const SKELETON_SECTIONS = 4;

function SupportSkeleton() {
  return (
    <Stack sx={{ gap: 4 }} role="status" aria-busy="true" aria-label="Caricamento dell'assistenza">
      {Array.from({ length: SKELETON_SECTIONS }, (_, index) => (
        <Stack key={index} sx={{ gap: 1 }}>
          <Skeleton variant="text" sx={{ width: 0.4, typography: 'h4' }} />
          <Skeleton variant="text" />
          <Skeleton variant="text" sx={{ width: 0.8 }} />
        </Stack>
      ))}
    </Stack>
  );
}

export default function SupportPage() {
  const supportQuery = useSupportContent();
  const sections = supportQuery.data ?? [];

  useScrollToHash(supportQuery.isSuccess);

  return (
    <InfoPageLayout title="Assistenza">
      {supportQuery.isPending ? <SupportSkeleton /> : null}
      {supportQuery.isError ? (
        <ErrorState
          title="Non è stato possibile caricare l'assistenza"
          message="Controlla la connessione e riprova."
          onRetry={() => supportQuery.refetch()}
        />
      ) : null}
      {supportQuery.isSuccess && sections.length === 0 ? (
        <EmptyState
          title="Nessuna informazione disponibile"
          description="Al momento non ci sono informazioni da mostrare. Riprova più tardi."
        />
      ) : null}
      {sections.length > 0 ? (
        <Stack sx={{ gap: 4 }} divider={<Divider flexItem />}>
          {sections.map((section) => (
            <InfoSection
              key={section.id}
              id={section.id}
              title={section.title}
              paragraphs={section.paragraphs}
            />
          ))}
        </Stack>
      ) : null}
    </InfoPageLayout>
  );
}
