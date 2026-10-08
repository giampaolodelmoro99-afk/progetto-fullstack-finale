export function passesLuhn(digits) {
  if (!/^\d+$/.test(digits)) {
    return false;
  }

  let sum = 0;

  [...digits].reverse().forEach((character, index) => {
    let value = Number(character);

    if (index % 2 === 1) {
      value *= 2;

      if (value > 9) {
        value -= 9;
      }
    }

    sum += value;
  });

  return sum % 10 === 0;
}
