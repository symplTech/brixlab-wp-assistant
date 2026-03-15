import { getConfig } from './config';

export async function assistantChat(
  message: string,
  history: Array<{ role: string; content: string }>
): Promise<{
  type: 'text' | 'tool_use';
  content?: string;
  text?: string;
  toolName?: string;
  toolInput?: Record<string, unknown>;
  toolCallId?: string;
}> {
  const cfg = getConfig();
  const form = new FormData();
  form.append('action', 'brixlab_assistant_chat');
  form.append('nonce', cfg.nonce || '');
  form.append('message', message);
  form.append('history', JSON.stringify(history));

  const response = await fetch(cfg.ajax_url, {
    method: 'POST',
    credentials: 'same-origin',
    body: form,
  });

  const data = await response.json();
  if (!data.success) {
    throw new Error(data.data?.message || 'Assistant chat failed');
  }

  return data.data;
}

export async function assistantPreview(
  toolName: string,
  toolInput: Record<string, unknown>
): Promise<{ title: string; description?: string; changes: Array<{ type: string; label: string; from?: string; to?: string }> }> {
  const cfg = getConfig();
  const form = new FormData();
  form.append('action', 'brixlab_assistant_preview');
  form.append('nonce', cfg.nonce || '');
  form.append('tool_name', toolName);
  form.append('tool_input', JSON.stringify(toolInput));

  const response = await fetch(cfg.ajax_url, {
    method: 'POST',
    credentials: 'same-origin',
    body: form,
  });

  const data = await response.json();
  if (!data.success) {
    throw new Error(data.data?.message || 'Preview failed');
  }

  return data.data;
}

export async function assistantExecute(
  toolName: string,
  toolInput: Record<string, unknown>
): Promise<{ success: boolean; message: string; data?: Record<string, unknown> }> {
  const cfg = getConfig();
  const form = new FormData();
  form.append('action', 'brixlab_assistant_execute');
  form.append('nonce', cfg.nonce || '');
  form.append('tool_name', toolName);
  form.append('tool_input', JSON.stringify(toolInput));

  const response = await fetch(cfg.ajax_url, {
    method: 'POST',
    credentials: 'same-origin',
    body: form,
  });

  const data = await response.json();
  if (!data.success) {
    throw new Error(data.data?.message || 'Execution failed');
  }

  return data.data;
}
