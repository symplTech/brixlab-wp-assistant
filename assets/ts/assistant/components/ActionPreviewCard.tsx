import type { ActionPreview, ToolCall } from '../types';

interface ActionPreviewCardProps {
  preview: ActionPreview;
  onAccept: (toolCall: ToolCall) => void;
  onReject: (toolCall: ToolCall) => void;
  isExecuting?: boolean;
}

const ActionPreviewCard: React.FC<ActionPreviewCardProps> = ({
  preview,
  onAccept,
  onReject,
  isExecuting = false,
}) => {
  const resolved = preview.resolved === true;
  const disabled = isExecuting || resolved;

  return (
    <div className="brixlab-assistant-action-preview">
      <div className="brixlab-assistant-action-preview__header">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" />
        </svg>
        <span>{preview.title}</span>
      </div>

      {preview.description && (
        <p className="brixlab-assistant-action-preview__description">{preview.description}</p>
      )}

      <div className="brixlab-assistant-action-preview__changes">
        {preview.changes.map((change, i) => (
          <div key={i} className={`brixlab-assistant-action-preview__change brixlab-assistant-action-preview__change--${change.type}`}>
            <span className="brixlab-assistant-action-preview__change-label">{change.label}</span>
            {change.type === 'update' && change.from !== undefined && (
              <div className="brixlab-assistant-action-preview__diff">
                <span className="brixlab-assistant-action-preview__from">{change.from}</span>
                <span className="brixlab-assistant-action-preview__arrow">&rarr;</span>
                <span className="brixlab-assistant-action-preview__to">{change.to}</span>
              </div>
            )}
            {change.type === 'create' && change.to && (
              <span className="brixlab-assistant-action-preview__to">{change.to}</span>
            )}
          </div>
        ))}
      </div>

      <div className="brixlab-assistant-action-preview__actions">
        <button
          className="brixlab-assistant-btn brixlab-assistant-btn--primary brixlab-assistant-btn--sm"
          onClick={() => onAccept(preview.toolCall)}
          disabled={disabled}
        >
          {isExecuting ? 'Executing...' : 'Accept'}
        </button>
        <button
          className="brixlab-assistant-btn brixlab-assistant-btn--secondary brixlab-assistant-btn--sm"
          onClick={() => onReject(preview.toolCall)}
          disabled={disabled}
        >
          Reject
        </button>
      </div>
    </div>
  );
};

export default ActionPreviewCard;
