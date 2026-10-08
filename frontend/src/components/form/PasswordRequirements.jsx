import CheckCircleOutlined from '@mui/icons-material/CheckCircleOutlined';
import RadioButtonUncheckedOutlined from '@mui/icons-material/RadioButtonUncheckedOutlined';
import Stack from '@mui/material/Stack';
import Typography from '@mui/material/Typography';
import { VisuallyHidden } from '@/components/feedback/VisuallyHidden';
import { passwordRequirements } from '@/utils/passwordRules';

export function PasswordRequirements({ value = '', id }) {
  return (
    <Stack
      component="ul"
      id={id}
      aria-label="Requisiti della password"
      sx={{ gap: 0.5, m: 0, p: 0 }}
    >
      {passwordRequirements.map((requirement) => {
        const met = requirement.test(value);

        return (
          <Stack
            key={requirement.id}
            component="li"
            direction="row"
            sx={{ alignItems: 'center', gap: 1, listStyle: 'none' }}
          >
            {met ? (
              <CheckCircleOutlined fontSize="small" sx={{ color: 'success.main' }} />
            ) : (
              <RadioButtonUncheckedOutlined fontSize="small" sx={{ color: 'text.disabled' }} />
            )}
            <Typography variant="body2" sx={{ color: met ? 'text.primary' : 'text.secondary' }}>
              {requirement.label}
              <VisuallyHidden>
                {met ? ', requisito soddisfatto' : ', requisito non ancora soddisfatto'}
              </VisuallyHidden>
            </Typography>
          </Stack>
        );
      })}
    </Stack>
  );
}
