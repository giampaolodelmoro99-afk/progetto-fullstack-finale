import List from '@mui/material/List';
import ListSubheader from '@mui/material/ListSubheader';

export function DrawerSection({ title, children }) {
  return (
    <List
      aria-label={title}
      subheader={
        <ListSubheader
          disableSticky
          sx={{
            typography: 'overline',
            color: 'text.secondary',
            bgcolor: 'transparent',
            pt: 2,
            pb: 1,
          }}
        >
          {title}
        </ListSubheader>
      }
    >
      {children}
    </List>
  );
}
