import Inventory2Outlined from '@mui/icons-material/Inventory2Outlined';
import PersonOutlineOutlined from '@mui/icons-material/PersonOutlineOutlined';
import PlaceOutlined from '@mui/icons-material/PlaceOutlined';
import ShieldOutlined from '@mui/icons-material/ShieldOutlined';
import SpaceDashboardOutlined from '@mui/icons-material/SpaceDashboardOutlined';
import { paths } from '@/app/router/paths';

export const accountNavigationItems = [
  { label: 'Profilo', icon: PersonOutlineOutlined, to: paths.accountProfile, audience: 'all' },
  { label: 'Indirizzi', icon: PlaceOutlined, to: paths.accountAddresses, audience: 'customer' },
  {
    label: 'I miei ordini',
    icon: Inventory2Outlined,
    to: paths.accountOrders,
    audience: 'customer',
  },
  { label: 'Sicurezza', icon: ShieldOutlined, to: paths.accountSecurity, audience: 'all' },
  { label: 'Amministrazione', icon: SpaceDashboardOutlined, to: paths.admin, audience: 'admin' },
];

export function getAccountNavigation(isAdmin) {
  const role = isAdmin ? 'admin' : 'customer';

  return accountNavigationItems.filter((item) => item.audience === 'all' || item.audience === role);
}

export function isAccountItemActive(item, pathname) {
  return pathname === item.to || pathname.startsWith(`${item.to}/`);
}
