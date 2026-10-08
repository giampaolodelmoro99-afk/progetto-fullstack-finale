import { browserSupportsWebAuthn } from '@simplewebauthn/browser';
import { useState } from 'react';

export function usePasskeySupport() {
  const [supported] = useState(() => browserSupportsWebAuthn());

  return supported;
}
