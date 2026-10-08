import Alert from '@mui/material/Alert';
import Divider from '@mui/material/Divider';
import Stack from '@mui/material/Stack';
import { InfoPageLayout } from '@/features/info/components/InfoPageLayout';
import { InfoSection } from '@/features/info/components/InfoSection';
import { legalContent, legalNotice } from '@/features/info/legalContent';

export default function LegalPage() {
  return (
    <InfoPageLayout title="Note legali">
      <Alert severity="info">{legalNotice}</Alert>
      <Stack sx={{ gap: 4 }} divider={<Divider flexItem />}>
        {legalContent.map((section) => (
          <InfoSection
            key={section.id}
            id={section.id}
            title={section.title}
            paragraphs={section.paragraphs}
          />
        ))}
      </Stack>
    </InfoPageLayout>
  );
}
