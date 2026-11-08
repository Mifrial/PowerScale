import { defineAsyncComponent } from 'vue';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import { registerMenuItem } from '@/modules/Core/UI/init';
import { NOTES_MENU_ITEM } from '@/modules/Messages/Notes/Constant/Navigation/NOTES_MENU_ITEM';
import type { INoteApi } from '@/modules/Messages/Notes/Interface/INoteApi';
import type { NoteHost } from '@/modules/Messages/Notes/Dto/NoteHost';
import { noteHostRegistry } from '@/modules/Messages/Notes/Service/Instance/noteHostRegistry';

export const NotesSlider = defineAsyncComponent(() => import('@/modules/Messages/Notes/Component/NotesSlider.vue'));

export function registerNotesApi(api: INoteApi): void {
  serviceLocator.set('Messages.Notes.Service.NoteApi', api);
}

export function getNoteApi(): INoteApi {
  return serviceLocator.get('Messages.Notes.Service.NoteApi');
}

export function registerNoteHost(host: NoteHost): void {
  noteHostRegistry.register(host);
}

export function getNoteHost(type: string): NoteHost | undefined {
  return noteHostRegistry.get(type);
}

export function getNoteHosts(): NoteHost[] {
  return noteHostRegistry.list();
}

export function registerNotesModule(): void {
  registerMenuItem(NOTES_MENU_ITEM);
}
