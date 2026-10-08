import ChevronRight from '@mui/icons-material/ChevronRight';
import Avatar from '@mui/material/Avatar';
import Box from '@mui/material/Box';
import Card from '@mui/material/Card';
import CardActionArea from '@mui/material/CardActionArea';
import Container from '@mui/material/Container';
import Grid from '@mui/material/Grid';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import ListItemAvatar from '@mui/material/ListItemAvatar';
import ListItemButton from '@mui/material/ListItemButton';
import ListItemText from '@mui/material/ListItemText';
import Skeleton from '@mui/material/Skeleton';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { getCategoryImage } from '@/features/catalog/categoryImages';
import { useCategories } from '@/features/catalog/hooks/useCategories';
import { SectionHeading } from '@/features/home/components/SectionHeading';
import { useIsDesktop } from '@/hooks/useIsDesktop';

const SKELETON_ITEMS = 5;

function CategoryTile({ category }) {
  return (
    <Card sx={{ border: 0, bgcolor: 'transparent', height: 1 }}>
      <CardActionArea
        component={RouterLink}
        to={paths.category(category.slug)}
        sx={{ borderRadius: 2 }}
      >
        <Box
          component="img"
          src={getCategoryImage(category.slug)}
          alt=""
          sx={{
            width: 1,
            aspectRatio: '1 / 1',
            objectFit: 'cover',
            display: 'block',
            borderRadius: 2,
            bgcolor: 'background.media',
          }}
        />
        <Stack sx={{ pt: 1.5, gap: 0.25 }}>
          <Typography variant="subtitle1" component="h3">
            {category.name}
          </Typography>
          {category.description ? (
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              {category.description}
            </Typography>
          ) : null}
        </Stack>
      </CardActionArea>
    </Card>
  );
}

function CategoryTiles({ categories }) {
  return (
    <Grid component="ul" container spacing={3} sx={{ listStyle: 'none', m: 0, p: 0 }}>
      {categories.map((category) => (
        <Grid component="li" key={category.id} size={{ md: 'grow' }} sx={{ minWidth: 0 }}>
          <CategoryTile category={category} />
        </Grid>
      ))}
    </Grid>
  );
}

function CategoryList({ categories }) {
  return (
    <List disablePadding>
      {categories.map((category) => (
        <ListItem key={category.id} disablePadding divider>
          <ListItemButton
            component={RouterLink}
            to={paths.category(category.slug)}
            sx={{ px: 0, py: 1.5 }}
          >
            <ListItemAvatar sx={{ mr: 1 }}>
              <Avatar
                variant="rounded"
                src={getCategoryImage(category.slug)}
                alt=""
                sx={{
                  width: (theme) => theme.spacing(7),
                  height: (theme) => theme.spacing(7),
                  bgcolor: 'background.media',
                }}
              />
            </ListItemAvatar>
            <ListItemText
              primary={category.name}
              secondary={category.description}
              slotProps={{ primary: { variant: 'subtitle1', component: 'h3' } }}
            />
            <ChevronRight aria-hidden="true" />
          </ListItemButton>
        </ListItem>
      ))}
    </List>
  );
}

function CategoriesSkeleton({ isDesktop }) {
  return (
    <Grid container spacing={3} aria-hidden="true">
      {Array.from({ length: SKELETON_ITEMS }, (_, index) => (
        <Grid key={index} size={isDesktop ? 'grow' : 12}>
          {isDesktop ? (
            <Skeleton
              variant="rectangular"
              sx={{ width: 1, height: 'auto', aspectRatio: '1 / 1', borderRadius: 2 }}
            />
          ) : null}
          <Skeleton variant="text" sx={{ width: 0.6 }} />
          <Skeleton variant="text" sx={{ width: 0.8 }} />
        </Grid>
      ))}
    </Grid>
  );
}

export function FeaturedCategories() {
  const categoriesQuery = useCategories();
  const isDesktop = useIsDesktop();

  if (categoriesQuery.isError) {
    return null;
  }

  return (
    <Container
      component="section"
      maxWidth="lg"
      aria-labelledby="featured-categories-title"
      aria-busy={categoriesQuery.isPending}
      sx={{ py: { xs: 5, md: 8 } }}
    >
      <Stack sx={{ gap: { xs: 3, md: 4 } }}>
        <SectionHeading
          id="featured-categories-title"
          overline="Categorie"
          title="Scegli da dove iniziare"
        />
        {categoriesQuery.isPending ? <CategoriesSkeleton isDesktop={isDesktop} /> : null}
        {categoriesQuery.isSuccess && isDesktop ? (
          <CategoryTiles categories={categoriesQuery.data} />
        ) : null}
        {categoriesQuery.isSuccess && !isDesktop ? (
          <CategoryList categories={categoriesQuery.data} />
        ) : null}
      </Stack>
    </Container>
  );
}
