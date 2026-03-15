import { useState, useCallback } from 'react';
import type { ChatMessage, ToolCall, ActionPreview } from '../types';
import { assistantChat, assistantPreview, assistantExecute } from '../api/assistant';

export function useAssistantChat() {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [pendingPreview, setPendingPreview] = useState<ActionPreview | null>(null);

  const sendMessage = useCallback(async (message: string) => {
    setMessages(prev => [...prev, { role: 'user', content: message }]);
    setIsLoading(true);

    try {
      const history = messages
        .concat([{ role: 'user' as const, content: message }])
        .map(m => ({ role: m.role, content: m.content }));

      const response = await assistantChat(message, history);

      if (response.type === 'text') {
        setMessages(prev => [...prev, { role: 'assistant', content: response.content || '' }]);
      } else if (response.type === 'tool_use') {
        const toolCall: ToolCall = {
          name: response.toolName!,
          input: response.toolInput!,
          id: response.toolCallId!,
        };

        // Special handling: build_website tool triggers a custom event
        // for Theme Builder to listen to, instead of preview/execute
        if (toolCall.name === 'build_website') {
          const prompt = (toolCall.input as any).prompt || message;
          setMessages(prev => [
            ...prev,
            { role: 'assistant', content: response.text || 'Opening the Website Builder...' },
          ]);
          window.dispatchEvent(new CustomEvent('brixlab:build_website', { detail: { prompt } }));
          setIsLoading(false);
          return;
        }

        // Get preview from PHP
        try {
          const previewData = await assistantPreview(toolCall.name, toolCall.input);
          const preview: ActionPreview = {
            title: previewData.title,
            description: previewData.description,
            changes: previewData.changes.map(c => ({
              ...c,
              type: c.type as 'create' | 'update' | 'delete',
            })),
            toolCall,
          };
          setPendingPreview(preview);
          setMessages(prev => [
            ...prev,
            {
              role: 'assistant',
              content: response.text || `I'd like to use the ${toolCall.name} tool.`,
              toolCall,
              preview,
            },
          ]);
        } catch (previewErr) {
          setMessages(prev => [
            ...prev,
            {
              role: 'assistant',
              content: `Failed to preview action: ${previewErr instanceof Error ? previewErr.message : 'Unknown error'}`,
            },
          ]);
        }
      }
    } catch (err) {
      setMessages(prev => [
        ...prev,
        {
          role: 'assistant',
          content: `Sorry, something went wrong: ${err instanceof Error ? err.message : 'Unknown error'}`,
        },
      ]);
    } finally {
      setIsLoading(false);
    }
  }, [messages]);

  const markPreviewResolved = useCallback(() => {
    setMessages(prev => prev.map(m =>
      m.preview && !m.preview.resolved
        ? { ...m, preview: { ...m.preview, resolved: true } }
        : m
    ));
  }, []);

  const acceptAction = useCallback(async (toolCall: ToolCall) => {
    setIsLoading(true);
    setPendingPreview(null);
    markPreviewResolved();

    try {
      const result = await assistantExecute(toolCall.name, toolCall.input);

      setMessages(prev => [
        ...prev,
        {
          role: 'assistant',
          content: result.success ? result.message : `Action failed: ${result.message}`,
          toolResult: result,
        },
      ]);
    } catch (err) {
      setMessages(prev => [
        ...prev,
        {
          role: 'assistant',
          content: `Execution failed: ${err instanceof Error ? err.message : 'Unknown error'}`,
        },
      ]);
    } finally {
      setIsLoading(false);
    }
  }, [messages]);

  const rejectAction = useCallback(async (toolCall: ToolCall) => {
    setPendingPreview(null);
    markPreviewResolved();
    setMessages(prev => [
      ...prev,
      { role: 'user', content: `I rejected the ${toolCall.name} action.` },
    ]);

    setIsLoading(true);
    try {
      const history = messages.map(m => ({ role: m.role, content: m.content }));
      history.push({ role: 'user', content: `I rejected the ${toolCall.name} action. Please suggest an alternative or ask what I'd prefer.` });

      const response = await assistantChat(
        `I rejected the ${toolCall.name} action. Please suggest an alternative or ask what I'd prefer.`,
        history
      );

      if (response.type === 'text') {
        setMessages(prev => [...prev, { role: 'assistant', content: response.content || 'Understood. What would you like me to do instead?' }]);
      }
    } catch {
      setMessages(prev => [...prev, { role: 'assistant', content: 'Understood. What would you like me to do instead?' }]);
    } finally {
      setIsLoading(false);
    }
  }, [messages]);

  const reset = useCallback(() => {
    setMessages([]);
    setIsLoading(false);
    setPendingPreview(null);
  }, []);

  return {
    messages,
    isLoading,
    pendingPreview,
    sendMessage,
    acceptAction,
    rejectAction,
    reset,
  };
}
