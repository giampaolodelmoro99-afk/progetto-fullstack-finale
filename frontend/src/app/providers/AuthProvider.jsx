import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useCallback, useMemo } from 'react';
import { queryKeys } from '@/api/queryKeys';
import { endUserSession } from '@/api/session';
import { fetchMe, logout as logoutRequest } from '@/features/auth/api/authApi';
import { AuthContext } from '@/features/auth/hooks/useAuth';

export function AuthProvider({ children }) {
  const queryClient = useQueryClient();
  const meQuery = useQuery({
    queryKey: queryKeys.me,
    queryFn: fetchMe,
    staleTime: Infinity,
  });

  const { mutateAsync: logoutAsync } = useMutation({
    mutationFn: logoutRequest,
    onSuccess: () => {
      endUserSession(queryClient);
    },
  });

  const logout = useCallback(() => logoutAsync(), [logoutAsync]);

  const user = meQuery.data ?? null;

  const value = useMemo(
    () => ({
      user,
      isAuthenticated: user !== null,
      isAdmin: user?.role?.value === 'admin',
      isEmailVerified: Boolean(user?.email_verified_at),
      isLoading: meQuery.isPending,
      logout,
    }),
    [user, meQuery.isPending, logout],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
