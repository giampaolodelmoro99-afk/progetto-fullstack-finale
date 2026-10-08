export function applyFieldErrors(setError, fieldErrors, fields) {
  const applied = Object.entries(fieldErrors ?? {}).filter(([field]) => fields.includes(field));

  applied.forEach(([field, message], index) => {
    setError(field, { type: 'server', message }, { shouldFocus: index === 0 });
  });

  return applied.length > 0;
}
