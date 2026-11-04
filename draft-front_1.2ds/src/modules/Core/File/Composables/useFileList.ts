import { ref } from 'vue';
import { useGridData } from '@/modules/Core/UI/Composables/useGridData';
import { getFileApi } from '@/modules/Core/File/init';
import { fileQueryFromGridService } from '@/modules/Core/File/Service/Instance/fileQueryFromGridService';
import type { FileRecord } from '@/modules/Core/File/Dto/FileRecord';

export function useFileList() {
  const detailsOpen = ref(false);
  const selected = ref<FileRecord | null>(null);

  const grid = useGridData<FileRecord & Record<string, unknown>>({
    mode: 'server',
    loadPage: async (query, signal) => {
      const page = await getFileApi().findPage(fileQueryFromGridService.toQuery(query), signal);

      return { rows: page.items as (FileRecord & Record<string, unknown>)[], total: page.total };
    },
  });

  function onRowAction(payload: { action: string; row: Record<string, unknown> }): void {
    if (payload.action !== 'open' && payload.action !== 'details') return;
    selected.value = payload.row as unknown as FileRecord;
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
