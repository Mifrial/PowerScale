export interface IAttachmentProcessor<TPayload = unknown> {
  type: string;
  process(payload: TPayload): Promise<unknown>;
  describe?(payload: TPayload): string;
}
