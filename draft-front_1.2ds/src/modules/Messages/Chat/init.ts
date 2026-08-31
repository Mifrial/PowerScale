import type { Component } from 'vue';
import { defineAsyncComponent } from 'vue';
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator';
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi';
import type { ICommandHandler } from '@/modules/Messages/Chat/Interface/ICommandHandler';
import type { IRenderer } from '@/modules/Messages/Chat/Interface/IRenderer';
import type { IChatToolbarExtension } from '@/modules/Messages/Chat/Interface/IChatToolbarExtension';
import type { IAttachmentProcessor } from '@/modules/Messages/Chat/Interface/IAttachmentProcessor';
import type { ITokenSource } from '@/modules/Messages/Chat/Interface/ITokenSource';
import type { IChatType } from '@/modules/Messages/Chat/Interface/IChatType';
import type { IChatTab } from '@/modules/Messages/Chat/Interface/IChatTab';
import type { IChatRulesProvider } from '@/modules/Messages/Chat/Interface/IChatRulesProvider';
import type { ChatRulesContext } from '@/modules/Messages/Chat/Dto/ChatRulesContext';
import { BASE_CHAT_TYPES } from '@/modules/Messages/Chat/Constant/Chat/BASE_CHAT_TYPES';
import { BASE_CHAT_TABS } from '@/modules/Messages/Chat/Constant/Chat/BASE_CHAT_TABS';
import { displayName, getUserApi, useUserCatalog } from '@/modules/Core/User/init';
import { chatInlineRendererRegistry } from '@/modules/Messages/Chat/Service/Instance/chatInlineRendererRegistry';

// Реэкспорт синтаксиса инлайн-токенов `[[type:param]]`: синтаксис един на все модули
// (персонажи/НПС в летописи используют те же токены, что чат).
export { INLINE_CONTENT_TOKEN_RE } from '@/modules/Messages/Chat/Constant/Chat/INLINE_CONTENT_TOKEN_RE';
export { chatInlineRendererContext } from '@/modules/Messages/Chat/Utils/chatInlineRendererContext';
export type { ChatInlineRendererContext } from '@/modules/Messages/Chat/Dto/ChatInlineRendererContext';
export { chatVisibilityService } from '@/modules/Messages/Chat/Service/Instance/chatVisibilityService';
export { chatFoldService } from '@/modules/Messages/Chat/Service/Instance/chatFoldService';
export { useChatChannel } from '@/modules/Messages/Chat/Composables/useChatChannel';
export { CHAT_PERMISSION_SEE_ALL } from '@/modules/Messages/Chat/Constant/Chat/CHAT_PERMISSION_SEE_ALL';

export const InlineTokenPicker = defineAsyncComponent(
  () => import('@/modules/Messages/Chat/Component/InlineTokenPicker.vue'),
);

const userChip = () => import('@/modules/Messages/Chat/Component/ChatUserChip.vue');

export function registerChatApi(api: IChatApi): void {
  serviceLocator.set('Messages.Chat.Service.ChatApi', api);
}

export function getChatApi(): IChatApi {
  return serviceLocator.get('Messages.Chat.Service.ChatApi');
}

const commandHandlers: ICommandHandler[] = [];
const contentRenderers = new Map<string, IRenderer>();
const toolbarExtensions: IChatToolbarExtension[] = [];
const attachmentProcessors = new Map<string, IAttachmentProcessor>();
const tokenSources: ITokenSource[] = [];
const chatRulesProviders: IChatRulesProvider[] = [];

const chatTypes: IChatType[] = [...BASE_CHAT_TYPES];
const chatTabs: IChatTab[] = [...BASE_CHAT_TABS];

chatInlineRendererRegistry.register({
  type: 'user',
  component: defineAsyncComponent(userChip),
  describe: (segment) => {
    const login = segment.params[0] ?? '';
    const user = useUserCatalog().findByLogin(login);
    if (!user) return login;

    return displayName(user.name, user.surname, user.login);
  },
});

registerTokenSource({
  type: 'user',
  label: 'Пользователь',
  icon: 'mdi-account',
  search: async (query) => {
    const catalogStore = useUserCatalog();
    let catalog = catalogStore.users.value;
    if (!catalog.length) {
      catalog = await getUserApi().getUsers();
    }
    const q = query.toLowerCase();
    const matched = q
      ? catalog.filter(
          (u) =>
            u.login.toLowerCase().includes(q) ||
            (u.name ?? '').toLowerCase().includes(q) ||
            (u.nickname ?? '').toLowerCase().includes(q),
        )
      : catalog.slice(0, 10);

    return matched.map((u) => ({
      value: u.login,
      label: displayName(u.name, u.surname, u.login),
    }));
  },
});

export function registerCommandHandler(handler: ICommandHandler): void {
  commandHandlers.push(handler);
}

export function getCommandHandlers(): ICommandHandler[] {
  return commandHandlers;
}

export function registerContentRenderer(renderer: IRenderer): void {
  contentRenderers.set(renderer.type, renderer);
}

export function getContentRenderer(type: string): Component | undefined {
  return contentRenderers.get(type)?.component;
}

export function getContentRendererEntry(type: string): IRenderer | undefined {
  return contentRenderers.get(type);
}

export function registerToolbarExtension(extension: IChatToolbarExtension): void {
  toolbarExtensions.push(extension);
}

export function getToolbarExtensions(): IChatToolbarExtension[] {
  return toolbarExtensions;
}

export function registerAttachmentProcessor<TPayload>(processor: IAttachmentProcessor<TPayload>): void {
  attachmentProcessors.set(processor.type, processor);
}

export function getAttachmentProcessor(type: string): IAttachmentProcessor | undefined {
  return attachmentProcessors.get(type);
}

export function registerInlineRenderer(renderer: IRenderer): void {
  chatInlineRendererRegistry.register(renderer);
}

export function getInlineRenderer(type: string): IRenderer | undefined {
  return chatInlineRendererRegistry.get(type);
}

export function registerTokenSource(source: ITokenSource): void {
  tokenSources.push(source);
}

export function getTokenSources(): ITokenSource[] {
  return tokenSources;
}

export function registerChatRulesProvider(provider: IChatRulesProvider): void {
  chatRulesProviders.push(provider);
}

export function getChatRulesProviders(): IChatRulesProvider[] {
  return chatRulesProviders;
}

/** Контекст правил чата по типу и id: доменный провайдер отдаёт opaque ChatRulesContext. */
export async function resolveChatRules(type: string, chatId: number): Promise<ChatRulesContext | null> {
  const providers = getChatRulesProviders();
  const provider =
    providers.find((candidate) => candidate.types.includes(type)) ??
    providers.find((candidate) => candidate.types.length === 0);
  if (!provider) return null;

  return provider.resolve(type, chatId);
}

export function registerChatType(chatType: IChatType): void {
  chatTypes.push(chatType);
}

export function registerChatTypes(types: IChatType[]): void {
  chatTypes.push(...types);
}

export function getChatTypes(): IChatType[] {
  return chatTypes;
}

export function registerChatTab(tab: IChatTab): void {
  chatTabs.push(tab);
}

export function registerChatTabs(tabs: IChatTab[]): void {
  chatTabs.push(...tabs);
}

export function getChatTabs(): IChatTab[] {
  return [...chatTabs].sort((a, b) => (a.sortOrder ?? 0) - (b.sortOrder ?? 0));
}

export function getChatIcon(type: string): string {
  return getChatTypes().find((t) => t.type === type)?.icon ?? 'mdi-chat';
}

export function getChatColor(type: string): string {
  return getChatTypes().find((t) => t.type === type)?.color ?? 'grey';
}

// Встраиваемая лента по chatId (обсуждение персонажа/игры). Async — init не тянет .vue в node-тестах.
export const ChatThread = defineAsyncComponent(() => import('@/modules/Messages/Chat/Component/ChatThread.vue'));
