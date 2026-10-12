import Button from '@mui/material/Button';
import Divider from '@mui/material/Divider';
import List from '@mui/material/List';
import ListItem from '@mui/material/ListItem';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { Fragment } from 'react';
import { Link as RouterLink } from 'react-router';
import { paths } from '@/app/router/paths';
import { ProductThumbnail } from '@/components/data-display/ProductThumbnail';
import { AdminSectionCard } from '@/features/admin/components/AdminSectionCard';
import { StockStatusLabel } from '@/features/admin/components/StockStatusLabel';

export function LowStockCard({ products, thresholdText }) {
  return (
    <AdminSectionCard
      title="Scorte basse"
      disableContentPadding
      aside={
        thresholdText ? (
          <Typography variant="caption" sx={{ color: 'text.secondary' }}>
            {thresholdText}
          </Typography>
        ) : null
      }
    >
      <Divider />
      {products.length === 0 ? (
        <Typography variant="body2" sx={{ color: 'text.secondary', p: 3 }}>
          Nessun prodotto sotto la soglia.
        </Typography>
      ) : (
        <List disablePadding aria-label="Prodotti con scorte basse">
          {products.map((product, index) => (
            <Fragment key={product.id}>
              {index > 0 ? <Divider component="li" /> : null}
              <ListItem sx={{ px: 3, py: 1.5, gap: 2 }}>
                <ProductThumbnail cover={product.cover} name={product.name} />
                <Stack sx={{ flexGrow: 1, minWidth: 0 }}>
                  <Typography variant="subtitle2" component="p">
                    {product.name}
                  </Typography>
                  <StockStatusLabel stockStatus={product.stock_status} />
                </Stack>
                <Button
                  component={RouterLink}
                  to={paths.adminProductEdit(product.id)}
                  aria-label={`Modifica ${product.name}`}
                >
                  Modifica
                </Button>
              </ListItem>
            </Fragment>
          ))}
        </List>
      )}
    </AdminSectionCard>
  );
}
