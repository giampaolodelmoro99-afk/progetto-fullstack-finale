import Send from '@mui/icons-material/Send';
import Box from '@mui/material/Box';
import IconButton from '@mui/material/IconButton';
import Stack from '@mui/material/Stack';
import TextField from '@mui/material/TextField';
import Typography from '@mui/material/Typography';
import { useState } from 'react';
import { VisuallyHidden } from '@/components/feedback/VisuallyHidden';
import { MAX_MESSAGE_LENGTH } from '@/features/assistant/assistantConstants';
import { useAssistant } from '@/features/assistant/hooks/useAssistant';

function secondsLabel(seconds) {
  return seconds === 1 ? '1 secondo' : `${seconds} secondi`;
}

export function AssistantComposer() {
  const { status, isBlocking, secondsLeft, send, registerInput } = useAssistant();
  const [text, setText] = useState('');
  const busy = status === 'waiting' || status === 'streaming';
  const disabled = busy || isBlocking;
  const question = text.trim();
  const helper = busy
    ? "L'assistente sta rispondendo."
    : isBlocking
      ? `Potrai scrivere tra ${secondsLabel(secondsLeft)}.`
      : '';

  function handleSubmit(event) {
    event.preventDefault();

    if (disabled || question === '') {
      return;
    }

    send(question);
    setText('');
  }

  return (
    <Box component="form" onSubmit={handleSubmit} noValidate sx={{ px: 2, pt: 1.5, pb: 2 }}>
      <Stack direction="row" sx={{ alignItems: 'flex-start', gap: 1 }}>
        <TextField
          inputRef={registerInput}
          autoFocus
          fullWidth
          size="small"
          label="La tua domanda"
          placeholder="Scrivi una domanda"
          autoComplete="off"
          value={text}
          disabled={disabled}
          onChange={(event) => setText(event.target.value)}
          slotProps={{ htmlInput: { maxLength: MAX_MESSAGE_LENGTH } }}
          helperText={
            <Box component="span" sx={{ display: 'flex', justifyContent: 'space-between', gap: 1 }}>
              <span>{helper}</span>
              <Box component="span" sx={{ flexShrink: 0 }}>
                <span>{`${text.length}/${MAX_MESSAGE_LENGTH}`}</span>
                <VisuallyHidden> caratteri</VisuallyHidden>
              </Box>
            </Box>
          }
        />
        <IconButton
          type="submit"
          aria-label="Invia"
          disabled={disabled || question === ''}
          sx={{
            bgcolor: 'primary.main',
            color: 'primary.contrastText',
            borderRadius: 1,
            '&:hover': { bgcolor: 'primary.light' },
            '&.Mui-disabled': { bgcolor: 'action.disabledBackground' },
          }}
        >
          <Send fontSize="small" />
        </IconButton>
      </Stack>
      <Typography variant="caption" component="p" sx={{ color: 'text.secondary', mt: 1 }}>
        Le risposte sono generate da un'AI: prezzi e disponibilità sono quelli delle schede.
      </Typography>
    </Box>
  );
}
