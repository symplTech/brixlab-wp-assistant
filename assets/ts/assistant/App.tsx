import { useState, useCallback } from 'react';
import { useAssistantChat } from './hooks/useAssistantChat';
import ChatBubble from './components/ChatBubble';
import AssistantChat from './components/AssistantChat';

type ViewMode = 'closed' | 'popover' | 'fullscreen';

const App: React.FC = () => {
  const [mode, setMode] = useState<ViewMode>('closed');
  const chat = useAssistantChat();

  const toggleBubble = useCallback(() => {
    setMode(prev => prev === 'closed' ? 'popover' : 'closed');
  }, []);

  const goFullscreen = useCallback(() => {
    setMode('fullscreen');
  }, []);

  const exitFullscreen = useCallback(() => {
    setMode('popover');
  }, []);

  const close = useCallback(() => {
    setMode('closed');
  }, []);

  const isOpen = mode !== 'closed';

  const chatElement = (
    <AssistantChat
      messages={chat.messages}
      onSubmit={chat.sendMessage}
      isLoading={chat.isLoading}
      placeholder="What would you like to do?"
      onAcceptAction={chat.acceptAction}
      onRejectAction={chat.rejectAction}
      isActionPending={chat.isLoading}
    />
  );

  return (
    <>
      {/* Bubble — hidden when fullscreen */}
      {mode !== 'fullscreen' && (
        <ChatBubble onClick={toggleBubble} isOpen={isOpen} />
      )}

      {/* Popover panel */}
      {mode === 'popover' && (
        <div className="brixlab-assistant-panel">
          <div className="brixlab-assistant-panel__header">
            <h3>AI Assistant</h3>
            <div className="brixlab-assistant-panel__header-actions">
              <button
                className="brixlab-assistant-panel__expand"
                onClick={goFullscreen}
                aria-label="Expand to fullscreen"
                title="Fullscreen"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="15 3 21 3 21 9" />
                  <polyline points="9 21 3 21 3 15" />
                  <line x1="21" y1="3" x2="14" y2="10" />
                  <line x1="3" y1="21" x2="10" y2="14" />
                </svg>
              </button>
              <button
                className="brixlab-assistant-panel__close"
                onClick={close}
                aria-label="Close"
              >
                &times;
              </button>
            </div>
          </div>

          <div className="brixlab-assistant-panel__body">
            {chatElement}
          </div>
        </div>
      )}

      {/* Fullscreen view */}
      {mode === 'fullscreen' && (
        <div className="brixlab-assistant-fullscreen">
          <div className="brixlab-assistant-fullscreen__header">
            <h3>AI Assistant</h3>
            <div className="brixlab-assistant-fullscreen__actions">
              <button
                className="brixlab-assistant-fullscreen__close"
                onClick={exitFullscreen}
                aria-label="Exit fullscreen"
                title="Exit fullscreen"
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <polyline points="4 14 10 14 10 20" />
                  <polyline points="20 10 14 10 14 4" />
                  <line x1="14" y1="10" x2="21" y2="3" />
                  <line x1="3" y1="21" x2="10" y2="14" />
                </svg>
              </button>
              <button
                className="brixlab-assistant-fullscreen__close"
                onClick={close}
                aria-label="Close"
              >
                &times;
              </button>
            </div>
          </div>

          <div className="brixlab-assistant-fullscreen__body">
            {chatElement}
          </div>
        </div>
      )}
    </>
  );
};

export default App;
