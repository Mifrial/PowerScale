import type { Component } from 'vue'
import type { DiceRollSpec } from '@/modules/Roleplay/Game/Dto/DiceRollSpec'

export interface ChatToolbarContext {
  pendingRolls: DiceRollSpec[]
  addRoll(spec: DiceRollSpec): void
  removeRoll(index: number): void
  send(text: string, rolls: DiceRollSpec[]): void
  disabled: boolean
}

export interface IChatToolbarExtension {
  id: string
  component: Component
}
