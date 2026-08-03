import { ref } from 'vue';
import type { ComputedRef, Ref } from 'vue';
import type { ColumnDefinition } from '@/modules/Core/UI/Dto/ColumnDefinition';

export interface ColumnDropTarget {
  key: string;
  side: 'before' | 'after';
}

export interface ColumnDragApi {
  dragKey: Ref<string | null>;
  dropTarget: Ref<ColumnDropTarget | null>;
  start: (key: string, e: DragEvent) => void;
  enter: (key: string, e: DragEvent) => void;
  drop: (key: string, e: DragEvent) => void;
  end: () => void;
}

export function useColumnDrag(options: {
  displayColumns: ComputedRef<ColumnDefinition[]>;
  saveOrder: (keys: string[]) => void;
}): ColumnDragApi {
  const dragKey = ref<string | null>(null);
  const dropTarget = ref<ColumnDropTarget | null>(null);

  function start(key: string, e: DragEvent) {
    dragKey.value = key;
    if (e.dataTransfer) {
      e.dataTransfer.effectAllowed = 'move';
    }
    const el = e.currentTarget as HTMLElement;
    const ghost = el.cloneNode(true) as HTMLElement;
    ghost.style.cssText = `
      position: absolute; top: -9999px;
      background: rgb(var(--v-theme-surface));
      box-shadow: 0 4px 16px rgba(var(--v-theme-scrim), var(--v-shadow-md-opacity));
      border: 1px solid rgb(var(--v-theme-primary));
      border-radius: 4px;
      padding: 8px 16px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      white-space: nowrap;
      opacity: 1;
    `;
    document.body.appendChild(ghost);
    if (e.dataTransfer) e.dataTransfer.setDragImage(ghost, 0, 0);
    requestAnimationFrame(() => {
      document.body.removeChild(ghost);
    });
  }

  function enter(key: string, e: DragEvent) {
    if (!key || !dragKey.value || dragKey.value === key) {
      dropTarget.value = null;

      return;
    }
    const th = e.currentTarget as HTMLElement;
    const rect = th.getBoundingClientRect();
    const mid = rect.left + rect.width / 2;
    dropTarget.value = { key, side: e.clientX < mid ? 'before' : 'after' };
  }

  function drop(key: string, _e: DragEvent) {
    if (!key || !dragKey.value || dragKey.value === key || !dropTarget.value) {
      dragKey.value = null;
      dropTarget.value = null;

      return;
    }
    const display = options.displayColumns.value.map((c) => c.key);
    const fromIdx = display.indexOf(dragKey.value);
    const toIdx = display.indexOf(key);
    if (fromIdx === -1 || toIdx === -1) {
      dragKey.value = null;
      dropTarget.value = null;

      return;
    }
    display.splice(fromIdx, 1);
    const newToIdx = display.indexOf(key);
    const insertAt = dropTarget.value.side === 'before' ? newToIdx : newToIdx + 1;
    display.splice(insertAt, 0, dragKey.value);
    options.saveOrder(display);
    dragKey.value = null;
    dropTarget.value = null;
  }

  function end() {
    dragKey.value = null;
    dropTarget.value = null;
  }

  return { dragKey, dropTarget, start, enter, drop, end };
}
