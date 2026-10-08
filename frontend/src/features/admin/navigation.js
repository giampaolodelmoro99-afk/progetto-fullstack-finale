import CategoryOutlined from '@mui/icons-material/CategoryOutlined';
import Inventory2Outlined from '@mui/icons-material/Inventory2Outlined';
import LocalMallOutlined from '@mui/icons-material/LocalMallOutlined';
import SpaceDashboardOutlined from '@mui/icons-material/SpaceDashboardOutlined';
import { paths } from '@/app/router/paths';

export const adminNavigationItems = [
  { label: 'Dashboard', icon: SpaceDashboardOutlined, to: paths.admin, exact: true },
  { label: 'Prodotti', icon: Inventory2Outlined, to: paths.adminProducts },
  { label: 'Categorie', icon: CategoryOutlined, to: paths.adminCategories },
  { label: 'Ordini', icon: LocalMallOutlined, to: paths.adminOrders },
];

export function isAdminItemActive(item, pathname) {
  if (item.exact) {
    return pathname === item.to;
  }

  return pathname === item.to || pathname.startsWith(`${item.to}/`);
}
