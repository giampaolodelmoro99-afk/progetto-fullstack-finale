import { useQuery } from '@tanstack/react-query';
import { queryKeys } from '@/api/queryKeys';
import { fetchAssistantStatus } from '@/features/assistant/api/assistantApi';

const UNAVAILABLE_REFETCH_MILLISECONDS = 30_000;

export function useAssistantStatus() {
  return useQuery({
    queryKey: queryKeys.assistant.status,
    queryFn: fetchAssistantStatus,
    refetchInterval: (query) =>
      query.state.data?.available === true ? false : UNAVAILABLE_REFETCH_MILLISECONDS,
  });
}
