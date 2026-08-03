import { type Component } from 'vue';

export interface FilterHandlerEntry {
  component: Component;
}

const handlers = new Map<string, FilterHandlerEntry>();

export function registerFilterHandler(type: string, entry: FilterHandlerEntry) {
  handlers.set(type, entry);
}

export function getFilterHandler(type: string): FilterHandlerEntry | undefined {
  return handlers.get(type);
}
