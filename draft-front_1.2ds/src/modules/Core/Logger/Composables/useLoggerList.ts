import { ref } from 'vue';
import { useGridData } from '@/modules/Core/UI/Composables/useGridData';
import { getLoggerApi } from '@/modules/Core/Logger/init';
import { logQueryFromGridService } from '@/modules/Core/Logger/Service/Instance/logQueryFromGridService';
import type { LogEntry } from '@/modules/Core/Logger/Dto/LogEntry';

export function useLoggerList() {
  const detailsOpen = ref(false);
  const selected = ref<LogEntry | null>(null);

  const grid = useGridData<LogEntry & Record<string, unknown>>({
    mode: 'server',
    loadPage: async (query, signal) => {
      const page = await getLoggerApi().findPage(logQueryFromGridService.toQuery(query), signal);

      return { rows: page.items as (LogEntry & Record<string, unknown>)[], total: page.total };
    },
  });

  function onRowAction(payload: { action: string; row: Record<string, unknown> }): void {
    if (payload.action !== 'open' && payload.action !== 'details') return;
    selected.value = payload.row as unknown as LogEntry;
    detailsOpen.value = true;
  }

  function closeDetails(): void {
    detailsOpen.value = false;
  }

  return {
    ...grid,
    detailsOpen,
    selected,
    onRowAction,
    closeDetails,
  };
}
