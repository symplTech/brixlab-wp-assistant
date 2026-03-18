export interface ToolCall {
  name: string;
  input: Record<string, unknown>;
  id: string;
}

export interface ActionChange {
  type: 'create' | 'update' | 'delete';
  label: string;
  from?: string;
  to?: string;
}

export interface ActionPreview {
  title: string;
  description?: string;
  changes: ActionChange[];
  toolCall: ToolCall;
  resolved?: boolean;
}

export interface ToolResultLink {
  url: string;
  label: string;
}

export interface ToolResult {
  success: boolean;
  message: string;
  link?: ToolResultLink;
  data?: Record<string, unknown>;
}

export interface ReadStep {
  toolName: string;
  action: string;
  input: Record<string, unknown>;
  result: string;
}

export interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
  toolCall?: ToolCall;
  preview?: ActionPreview;
  toolResult?: ToolResult;
  readSteps?: ReadStep[];
}
