import { useState, useCallback, useRef } from 'react';
import type { ChatMessage, ToolCall, ActionPreview, ReadStep } from '../types';
import { assistantChat, assistantPreview, assistantExecute } from '../api/assistant';

export function useAssistantChat() {
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [isLoading, setIsLoading] = useState(false);
  const [pendingPreview, setPendingPreview] = useState<ActionPreview | null>(null);

  // Use a ref to always have current messages for history building
  const messagesRef = useRef<ChatMessage[]>([]);
  messagesRef.current = messages;

  /**
   * Build conversation history from current messages.
   */
  const buildHistory = useCallback((extraMessages: Array<{ role: 'user' | 'assistant'; content: string }> = []) => {
    const history: Array<{ role: 'user' | 'assistant'; content: string }> = messagesRef.current
      .map(m => ({ role: m.role, content: m.content }));
    return history.concat(extraMessages);
  }, []);

  /**
   * Handle an AI response — may be text or a tool_use.
   * Returns true if the response was fully handled (text or preview shown),
   * false if it needs further processing.
   */
  const handleAIResponse = useCallback(async (
    response: { type: string; content?: string; text?: string; toolName?: string; toolInput?: Record<string, unknown>; toolCallId?: string; readSteps?: ReadStep[] },
    userMessage?: string,
  ) => {
    const readSteps = response.readSteps;

    // If there were read steps, add them as a collapsed message before the main response
    if (readSteps && readSteps.length > 0) {
      setMessages(prev => [...prev, { role: 'assistant', content: '', readSteps }]);
    }

    if (response.type === 'text') {
      setMessages(prev => [...prev, { role: 'assistant', content: response.content || '' }]);
      setIsLoading(false);
      return;
    }

    if (response.type === 'tool_use') {
      const toolCall: ToolCall = {
        name: response.toolName!,
        input: response.toolInput!,
        id: response.toolCallId!,
      };

      // Special handling: build_website tool
      if (toolCall.name === 'build_website') {
        const prompt = (toolCall.input as any).prompt || userMessage || '';
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
      setIsLoading(false);
    }
  }, []);

  const sendMessage = useCallback(async (message: string) => {
    setMessages(prev => [...prev, { role: 'user', content: message }]);
    setIsLoading(true);

    try {
      const history = buildHistory([{ role: 'user', content: message }]);
      const response = await assistantChat(message, history);
      await handleAIResponse(response, message);
    } catch (err) {
      setMessages(prev => [
        ...prev,
        {
          role: 'assistant',
          content: `Sorry, something went wrong: ${err instanceof Error ? err.message : 'Unknown error'}`,
        },
      ]);
      setIsLoading(false);
    }
  }, [buildHistory, handleAIResponse]);

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

      const resultMessage = result.success ? result.message : `Action failed: ${result.message}`;

      setMessages(prev => [
        ...prev,
        {
          role: 'assistant',
          content: resultMessage,
          toolResult: result,
        },
      ]);

      // Feed the result back to the AI so it can continue with the next step
      // (e.g. user said "remove X and add Y" — after removing, AI should add)
      const continuationMessage = `[Tool "${toolCall.name}" executed] Result: ${resultMessage}. If there are more steps to complete from the original request, proceed with the next one. If everything is done, summarize what was accomplished.`;

      const history = buildHistory([
        { role: 'assistant', content: resultMessage },
        { role: 'user', content: continuationMessage },
      ]);

      const response = await assistantChat(continuationMessage, history);
      await handleAIResponse(response);
    } catch (err) {
      setMessages(prev => [
        ...prev,
        {
          role: 'assistant',
          content: `Execution failed: ${err instanceof Error ? err.message : 'Unknown error'}`,
        },
      ]);
      setIsLoading(false);
    }
  }, [buildHistory, handleAIResponse, markPreviewResolved]);

  const rejectAction = useCallback(async (toolCall: ToolCall) => {
    setPendingPreview(null);
    markPreviewResolved();

    const rejectMessage = `I rejected the ${toolCall.name} action. Please suggest an alternative or ask what I'd prefer.`;
    setMessages(prev => [
      ...prev,
      { role: 'user', content: `I rejected the ${toolCall.name} action.` },
    ]);

    setIsLoading(true);
    try {
      const history = buildHistory([{ role: 'user', content: rejectMessage }]);
      const response = await assistantChat(rejectMessage, history);
      await handleAIResponse(response);
    } catch {
      setMessages(prev => [...prev, { role: 'assistant', content: 'Understood. What would you like me to do instead?' }]);
      setIsLoading(false);
    }
  }, [buildHistory, handleAIResponse, markPreviewResolved]);

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
