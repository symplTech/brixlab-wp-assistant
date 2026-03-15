import { useState } from 'react';
import { useAssistantChat } from './hooks/useAssistantChat';
import ChatBubble from './components/ChatBubble';
import AssistantChat from './components/AssistantChat';

const App: React.FC = () => {
  const [isOpen, setIsOpen] = useState(false);
  const chat = useAssistantChat();

  const togglePanel = () => {
    setIsOpen(!isOpen);
  };

  return (
    <>
      <ChatBubble onClick={togglePanel} isOpen={isOpen} />

      {isOpen && (
        <div className="brixlab-assistant-panel">
          <div className="brixlab-assistant-panel__header">
            <h3>AI Assistant</h3>
            <button
              className="brixlab-assistant-panel__close"
              onClick={togglePanel}
              aria-label="Close"
            >
              &times;
            </button>
          </div>

          <div className="brixlab-assistant-panel__body">
            <AssistantChat
              messages={chat.messages}
              onSubmit={chat.sendMessage}
              isLoading={chat.isLoading}
              placeholder="What would you like to do?"
              onAcceptAction={chat.acceptAction}
              onRejectAction={chat.rejectAction}
              isActionPending={chat.isLoading}
            />
          </div>
        </div>
      )}
    </>
  );
};

export default App;
