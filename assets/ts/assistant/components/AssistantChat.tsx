import { useState, useRef, useEffect, useCallback } from 'react';
import { marked } from 'marked';
import type { ChatMessage, ToolCall } from '../types';
import ActionPreviewCard from './ActionPreviewCard';
import ReadStepCard from './ReadStepCard';

marked.setOptions({ breaks: true, gfm: true });

function renderMarkdown(content: string): string {
  return marked.parse(content, { async: false }) as string;
}

interface AssistantChatProps {
  messages: ChatMessage[];
  onSubmit: (message: string) => void;
  isLoading?: boolean;
  placeholder?: string;
  suggestions?: string[];
  onAcceptAction?: (toolCall: ToolCall) => void;
  onRejectAction?: (toolCall: ToolCall) => void;
  isActionPending?: boolean;
  inputValue?: string;
  onInputChange?: (value: string) => void;
}

const AssistantChat: React.FC<AssistantChatProps> = ({
  messages,
  onSubmit,
  isLoading = false,
  placeholder = 'Ask me anything...',
  suggestions,
  onAcceptAction,
  onRejectAction,
  isActionPending = false,
  inputValue,
  onInputChange,
}) => {
  const [localInput, setLocalInput] = useState('');
  const input = inputValue !== undefined ? inputValue : localInput;
  const setInput = onInputChange || setLocalInput;
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  const isDisabled = isLoading || isActionPending;

  useEffect(() => {
    if (!isDisabled) {
      requestAnimationFrame(() => textareaRef.current?.focus());
    }
  }, [isDisabled, messages.length]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  const autoResize = useCallback(() => {
    const el = textareaRef.current;
    if (!el) return;
    el.style.height = 'auto';
    const capped = Math.min(el.scrollHeight, 150);
    el.style.height = `${capped}px`;
    el.style.overflowY = el.scrollHeight > 150 ? 'auto' : 'hidden';
  }, []);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (input.trim() && !isLoading) {
      onSubmit(input.trim());
      setInput('');
      requestAnimationFrame(() => {
        if (textareaRef.current) {
          textareaRef.current.style.height = 'auto';
          textareaRef.current.focus();
        }
      });
    }
  };

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      handleSubmit(e);
    }
  };

  const defaultSuggestions = [
    'Change the site title to "My Awesome Site"',
    'Create a new page called "About Us"',
    'How do I add a custom menu in WordPress?',
    'What plugins are currently active?',
  ];

  const activeSuggestions = suggestions || defaultSuggestions;

  return (
    <div className="brixlab-assistant-chat">
      {messages.length === 0 ? (
        <>
          <div className="brixlab-assistant-chat__intro">
            <h4>AI Assistant</h4>
            <p>I can help manage your WordPress site. What would you like to do?</p>
          </div>

          <div className="brixlab-assistant-chat__suggestions">
            {activeSuggestions.map((suggestion, i) => (
              <button
                key={i}
                className="brixlab-assistant-chat__suggestion"
                onClick={() => onSubmit(suggestion)}
              >
                {suggestion}
              </button>
            ))}
          </div>
        </>
      ) : (
        <div className="brixlab-assistant-chat__messages">
          {messages.map((msg, i) => {
            // Read-only step message — render just the collapsed card
            if (msg.readSteps && msg.readSteps.length > 0 && !msg.content) {
              return (
                <div key={i} className="brixlab-assistant-msg brixlab-assistant-msg--assistant">
                  <ReadStepCard steps={msg.readSteps} />
                </div>
              );
            }

            return (
            <div
              key={i}
              className={`brixlab-assistant-msg brixlab-assistant-msg--${msg.role}`}
            >
              {msg.readSteps && msg.readSteps.length > 0 && (
                <ReadStepCard steps={msg.readSteps} />
              )}
              {msg.role === 'assistant' ? (
                <div
                  className="brixlab-assistant-msg__content brixlab-assistant-msg__markdown"
                  dangerouslySetInnerHTML={{
                    __html: renderMarkdown(msg.content)
                      + (msg.toolResult?.link
                        ? `<a class="brixlab-assistant-msg__link" href="${msg.toolResult.link.url}" target="_blank" rel="noopener noreferrer">${msg.toolResult.link.label} &rarr;</a>`
                        : ''),
                  }}
                />
              ) : (
                <div className="brixlab-assistant-msg__content">{msg.content}</div>
              )}
              {msg.preview && onAcceptAction && onRejectAction && (
                <ActionPreviewCard
                  preview={msg.preview}
                  onAccept={onAcceptAction}
                  onReject={onRejectAction}
                  isExecuting={isActionPending}
                />
              )}
            </div>
            );
          })}

          {isLoading && (
            <div className="brixlab-assistant-msg brixlab-assistant-msg--assistant">
              <div className="brixlab-assistant-msg__content brixlab-assistant-msg__typing">
                <span className="brixlab-assistant-typing-dot" />
                <span className="brixlab-assistant-typing-dot" />
                <span className="brixlab-assistant-typing-dot" />
              </div>
            </div>
          )}

          <div ref={messagesEndRef} />
        </div>
      )}

      <form className="brixlab-assistant-chat__form" onSubmit={handleSubmit}>
        <div className="brixlab-assistant-chat__input-row">
          <textarea
            ref={textareaRef}
            className="brixlab-assistant-chat__input"
            value={input}
            onChange={e => { setInput(e.target.value); autoResize(); }}
            onKeyDown={handleKeyDown}
            placeholder={placeholder}
            disabled={isLoading || isActionPending}
            rows={1}
          />
          <button
            type="submit"
            className="brixlab-assistant-chat__send"
            disabled={!input.trim() || isLoading || isActionPending}
            aria-label="Send"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
              <line x1="22" y1="2" x2="11" y2="13" />
              <polygon points="22 2 15 22 11 13 2 9 22 2" />
            </svg>
          </button>
        </div>
      </form>
    </div>
  );
};

export default AssistantChat;
