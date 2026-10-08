import Box from '@mui/material/Box';
import Container from '@mui/material/Container';
import { FeaturedCategories } from '@/features/home/components/FeaturedCategories';
import { HomeHero } from '@/features/home/components/HomeHero';
import { NewArrivals } from '@/features/home/components/NewArrivals';
import { Reassurances } from '@/features/settings/components/Reassurances';

export default function HomePage() {
  return (
    <>
      <HomeHero />
      <FeaturedCategories />
      <NewArrivals />
      <Box component="section" aria-label="I nostri servizi" sx={{ bgcolor: 'secondary.light' }}>
        <Container maxWidth="lg" sx={{ py: { xs: 4, md: 5 } }}>
          <Reassurances />
        </Container>
      </Box>
    </>
  );
}
