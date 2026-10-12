export const orderStatusColors = {
  pending: 'warning',
  paid: 'info',
  shipped: 'secondary',
  delivered: 'success',
  cancelled: 'error',
};

export const availabilityColors = {
  available: 'success.main',
  low_stock: 'warning.main',
  out_of_stock: 'text.secondary',
};

export const availabilityChipColors = {
  available: { color: 'success.main', bgcolor: 'success.light' },
  low_stock: { color: 'warning.main', bgcolor: 'warning.light' },
  out_of_stock: { color: 'text.secondary', bgcolor: 'background.media' },
};

export const stockStatusColors = {
  available: 'text.primary',
  low_stock: 'warning.main',
  out_of_stock: 'error.main',
};

export const paymentStatusColors = {
  succeeded: 'success.main',
  failed: 'error.main',
  refunded: 'text.secondary',
};
