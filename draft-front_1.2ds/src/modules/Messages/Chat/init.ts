import type { Component } from 'vue'
import { serviceLocator } from '@/modules/Core/Engine/Service/ServiceLocator'
import type { IChatApi } from '@/modules/Messages/Chat/Interface/IChatApi'
import type { ICommandHandler } from '@/modules/Messages/Chat/Interface/ICommandHandler'
import type { IContentRenderer } from '@/modules/Messages/Chat/Interface/IContentRenderer'
import type { IChatToolbarExtension } from '@/modules/Messages/Chat/Interface/IChatToolbarExtension'

export function registerChatApi(api: IChatApi): void {
  serviceLocator.set('Messages.Chat.Service.ChatApi', api)
}

export function getChatApi(): IChatApi {
  return serviceLocator.get('Messages.Chat.Service.ChatApi')
}

const commandHandlers: ICommandHandler[] = []
const contentRenderers = new Map<string, Component>()
const toolbarExtensions: IChatToolbarExtension[] = []

export function registerCommandHandler(handler: ICommandHandler): void {
  commandHandlers.push(handler)
}

export function getCommandHandlers(): ICommandHandler[] {
  return commandHandlers
}

export function registerContentRenderer(renderer: IContentRenderer): void {
  contentRenderers.set(renderer.type, renderer.component)
}

export function getContentRenderer(type: string): Component | undefined {
  return contentRenderers.get(type)
}

export function registerToolbarExtension(extension: IChatToolbarExtension): void {
  toolbarExtensions.push(extension)
}

export function getToolbarExtensions(): IChatToolbarExtension[] {
  return toolbarExtensions
}
