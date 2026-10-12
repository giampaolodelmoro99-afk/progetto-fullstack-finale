import Close from '@mui/icons-material/Close';
import SearchOutlined from '@mui/icons-material/SearchOutlined';
import IconButton from '@mui/material/IconButton';
import InputAdornment from '@mui/material/InputAdornment';
import TextField from '@mui/material/TextField';
import { useEffect, useRef, useState } from 'react';
import { MAX_SEARCH_LENGTH } from '@/features/admin/listParams';
import { useDebouncedValue } from '@/hooks/useDebouncedValue';

export const SEARCH_DELAY = 300;

export function SearchField({ value, onSearch, label, placeholder, sx }) {
  const [text, setText] = useState(value);
  const [appliedValue, setAppliedValue] = useState(value);
  const debouncedText = useDebouncedValue(text, SEARCH_DELAY);
  const latest = useRef({ value, onSearch });

  if (value !== appliedValue) {
    setAppliedValue(value);
    setText(value);
  }

  useEffect(() => {
    latest.current = { value, onSearch };
  });

  useEffect(() => {
    const search = debouncedText.trim();

    if (search !== latest.current.value) {
      latest.current.onSearch(search);
    }
  }, [debouncedText]);

  function clear() {
    setText('');
    onSearch('');
  }

  return (
    <TextField
      label={label}
      placeholder={placeholder}
      value={text}
      onChange={(event) => setText(event.target.value)}
      sx={sx}
      slotProps={{
        htmlInput: { maxLength: MAX_SEARCH_LENGTH },
        input: {
          startAdornment: (
            <InputAdornment position="start">
              <SearchOutlined />
            </InputAdornment>
          ),
          endAdornment:
            text === '' ? null : (
              <InputAdornment position="end">
                <IconButton aria-label="Cancella la ricerca" edge="end" onClick={clear}>
                  <Close />
                </IconButton>
              </InputAdornment>
            ),
        },
      }}
    />
  );
}
