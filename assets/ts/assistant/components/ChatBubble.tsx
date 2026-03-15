interface ChatBubbleProps {
  onClick: () => void;
  isOpen: boolean;
}

const ChatBubble: React.FC<ChatBubbleProps> = ({ onClick, isOpen }) => {
  return (
    <button
      className={`brixlab-assistant-bubble ${isOpen ? 'brixlab-assistant-bubble--open' : ''}`}
      onClick={onClick}
      aria-label={isOpen ? 'Close AI Assistant' : 'Open AI Assistant'}
      title="AI Assistant"
    >
      {isOpen ? (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <line x1="18" y1="6" x2="6" y2="18" />
          <line x1="6" y1="6" x2="18" y2="18" />
        </svg>
      ) : (
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />
        </svg>
      )}
    </button>
  );
};

export default ChatBubble;
