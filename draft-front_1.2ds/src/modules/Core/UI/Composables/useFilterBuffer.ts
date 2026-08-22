import { reactive, computed, watch } from 'vue';
import type { FilterValue } from '@/modules/Core/UI/Dto/Filter/Values/FilterValue';
import type { MaybeFilterValue } from '@/modules/Core/UI/Dto/Filter/MaybeFilterValue';
import type { ActiveChip } from '@/modules/Core/UI/Dto/Filter/ActiveChip';
import type { FilterBuffer } from '@/modules/Core/UI/Interface/Filter/FilterBuffer';
import type { FilterBufferOptions } from '@/modules/Core/UI/Interface/Filter/FilterBufferOptions';
import { debounce } from '@/modules/Core/UI/Utils/debounce';
import { FilterChipService } from '@/modules/Core/UI/Service/Filter/FilterChipService';

const filterChipService = new FilterChipService();

export function useFilterBuffer(options: FilterBufferOptions): FilterBuffer {
  const { fields, modelValue, menuOpen, onCommit } = options;

  const internal = reactive<Record<string, MaybeFilterValue>>({});
  const editBuffer = reactive<Record<string, MaybeFilterValue>>({});
  const enabled = reactive<Record<string, boolean>>({});

  watch(
    modelValue,
    (v) => {
      for (const [k, val] of Object.entries(v)) {
        internal[k] = val;
      }
      for (const k of Object.keys(internal)) {
        if (!(k in v)) delete internal[k];
      }
    },
    { immediate: true },
  );

  function emitNow() {
    const out: Record<string, FilterValue> = {};
    for (const [k, v] of Object.entries(internal)) {
      if (v !== undefined && v !== null && v !== '') {
        out[k] = v;
      }
    }
    onCommit(out);
  }

  const debouncedEmit = debounce(emitNow, 200);

  const searchText = computed<string>({
    get: () => (typeof internal.q === 'string' ? internal.q : ''),
    set: (v: string) => {
      internal.q = v || undefined;
      debouncedEmit();
      if (v && menuOpen.value) {
        menuOpen.value = false;
      }
    },
  });

  const activeChips = computed<ActiveChip[]>(() => filterChipService.buildChips(fields.value, internal));

  const hasActiveFilters = computed(() => activeChips.value.length > 0 || !!internal.q);

  function onValueUpdate(key: string, value: MaybeFilterValue) {
    editBuffer[key] = value;
    enabled[key] = true;
  }

  function setEnabled(key: string, value: boolean) {
    enabled[key] = value;
  }

  function syncOnOpen() {
    for (const f of fields.value) {
      const val = internal[f.key];
      editBuffer[f.key] = val ?? (f.type === 'select' ? null : '');
      enabled[f.key] = val !== undefined && val !== null && val !== '';
    }
  }

  watch(menuOpen, (open) => {
    if (open) syncOnOpen();
  });

  function apply() {
    for (const f of fields.value) {
      if (f.type === 'boolean') {
        if (editBuffer[f.key]) {
          internal[f.key] = true;
        } else {
          delete internal[f.key];
        }
      } else if (enabled[f.key]) {
        const val = editBuffer[f.key];
        if (val !== undefined && val !== null && val !== '') {
          internal[f.key] = val;
        } else {
          delete internal[f.key];
        }
      } else {
        delete internal[f.key];
      }
    }
    emitNow();
    menuOpen.value = false;
  }

  function resetAll() {
    for (const f of fields.value) {
      editBuffer[f.key] = '';
      enabled[f.key] = false;
      delete internal[f.key];
    }
    internal.q = undefined;
    emitNow();
    menuOpen.value = false;
  }

  function removeChip(key: string) {
    delete internal[key];
    emitNow();
  }

  return {
    editBuffer,
    enabled,
    searchText,
    activeChips,
    hasActiveFilters,
    onValueUpdate,
    setEnabled,
    removeChip,
    apply,
    resetAll,
  };
}
