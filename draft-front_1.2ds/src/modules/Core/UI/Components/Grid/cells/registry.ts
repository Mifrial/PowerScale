import { type Component } from 'vue'

const renderers = new Map<string, Component>()

export function registerRenderer(type: string, component: Component) {
  renderers.set(type, component)
}

export function getRenderer(type: string): Component | undefined {
  return renderers.get(type)
}
