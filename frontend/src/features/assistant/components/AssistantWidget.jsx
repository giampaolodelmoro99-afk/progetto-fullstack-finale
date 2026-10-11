import { AssistantLauncher } from '@/features/assistant/components/AssistantLauncher';
import { AssistantPanel } from '@/features/assistant/components/AssistantPanel';
import { AssistantProvider } from '@/features/assistant/components/AssistantProvider';
import { useAuth } from '@/features/auth/hooks/useAuth';

export function AssistantWidget() {
  const { isLoading, isAdmin } = useAuth();

  if (isLoading || isAdmin) {
    return null;
  }

  return (
    <AssistantProvider>
      <AssistantLauncher />
      <AssistantPanel />
    </AssistantProvider>
  );
}
