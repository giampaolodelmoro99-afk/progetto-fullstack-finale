import TextField from '@mui/material/TextField';
import { useId } from 'react';
import { Controller } from 'react-hook-form';

function describedSlotProps(slotProps, ids) {
  if (ids.length === 0) {
    return slotProps;
  }

  return {
    ...slotProps,
    htmlInput: { ...slotProps?.htmlInput, 'aria-describedby': ids.join(' ') },
  };
}

export function FormTextField({
  name,
  control,
  helperText,
  describedBy,
  id,
  slotProps,
  component: Field = TextField,
  ...props
}) {
  const generatedId = useId();
  const fieldId = id ?? generatedId;

  return (
    <Controller
      name={name}
      control={control}
      render={({ field: { ref, ...field }, fieldState }) => {
        const message = fieldState.error?.message ?? helperText;
        const ids = describedBy
          ? [message ? `${fieldId}-helper-text` : null, describedBy].filter(Boolean)
          : [];

        return (
          <Field
            {...props}
            {...field}
            id={fieldId}
            inputRef={ref}
            error={Boolean(fieldState.error)}
            helperText={message}
            slotProps={describedSlotProps(slotProps, ids)}
          />
        );
      }}
    />
  );
}
