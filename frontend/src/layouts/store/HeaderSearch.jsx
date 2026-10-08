import SearchOutlined from '@mui/icons-material/SearchOutlined';
import Box from '@mui/material/Box';
import InputAdornment from '@mui/material/InputAdornment';
import TextField from '@mui/material/TextField';
import { useState } from 'react';
import { useNavigate } from 'react-router';
import { paths } from '@/app/router/paths';

export function HeaderSearch({ initialValue = '', sx }) {
  const [value, setValue] = useState(initialValue);
  const navigate = useNavigate();

  function handleSubmit(event) {
    event.preventDefault();
    const query = value.trim();

    if (query === '') {
      return;
    }

    navigate(paths.productSearch(query));
  }

  return (
    <Box component="form" role="search" onSubmit={handleSubmit} sx={sx}>
      <TextField
        size="small"
        type="search"
        autoComplete="off"
        value={value}
        onChange={(event) => setValue(event.target.value)}
        placeholder="Cerca cuffie, caricatori, supporti"
        slotProps={{
          htmlInput: { 'aria-label': 'Cerca nel negozio', maxLength: 100 },
          input: {
            startAdornment: (
              <InputAdornment position="start">
                <SearchOutlined />
              </InputAdornment>
            ),
          },
        }}
      />
    </Box>
  );
}
