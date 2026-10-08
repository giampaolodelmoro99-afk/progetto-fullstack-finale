import { queryKeys } from '@/api/queryKeys';

function isMeQuery(query) {
  return query.queryKey[0] === queryKeys.me[0];
}

export function endUserSession(client) {
  const hadUser = Boolean(client.getQueryData(queryKeys.me));

  if (hadUser) {
    client.cancelQueries({ predicate: (query) => !isMeQuery(query) });
    client.removeQueries({ predicate: (query) => !isMeQuery(query) });
    client.getMutationCache().clear();
  }

  client.setQueryData(queryKeys.me, null);
}

export function startUserSession(client, user) {
  client.setQueryData(queryKeys.me, user);
  client.invalidateQueries({ queryKey: queryKeys.cart });
}
