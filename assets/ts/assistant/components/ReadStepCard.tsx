import { useState } from 'react';
import type { ReadStep } from '../types';

const TOOL_LABELS: Record<string, string> = {
  manage_menu: 'Menu',
  manage_post: 'Posts',
  manage_user: 'Users',
  manage_plugin: 'Plugins',
  manage_option: 'Settings',
};

function getStepLabel(step: ReadStep): string {
  const toolLabel = TOOL_LABELS[step.toolName] || step.toolName;
  const action = step.action;

  if (step.toolName === 'manage_menu') {
    if (action === 'list_menus') return 'Listed all menus';
    if (action === 'list_items') {
      const menuName = (step.input as any).menu_name || (step.input as any).menu_id || '';
      return menuName ? `Listed items in "${menuName}"` : 'Listed menu items';
    }
  }

  if (action === 'list') return `Listed ${toolLabel.toLowerCase()}`;
  if (action === 'get') return `Read ${toolLabel.toLowerCase()}`;
  if (action === 'get_all') return `Read all ${toolLabel.toLowerCase()}`;

  return `${toolLabel}: ${action}`;
}

interface ReadStepCardProps {
  steps: ReadStep[];
}

const ReadStepCard: React.FC<ReadStepCardProps> = ({ steps }) => {
  const [expanded, setExpanded] = useState(false);

  if (steps.length === 0) return null;

  return (
    <div className="brixlab-assistant-read-steps">
      <button
        className="brixlab-assistant-read-steps__toggle"
        onClick={() => setExpanded(!expanded)}
        type="button"
      >
        <svg
          className={`brixlab-assistant-read-steps__chevron ${expanded ? 'brixlab-assistant-read-steps__chevron--open' : ''}`}
          width="14" height="14" viewBox="0 0 24 24" fill="none"
          stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"
        >
          <polyline points="9 18 15 12 9 6" />
        </svg>
        <span className="brixlab-assistant-read-steps__label">
          {steps.length === 1
            ? getStepLabel(steps[0])
            : `${steps.length} lookups performed`
          }
        </span>
      </button>

      {expanded && (
        <div className="brixlab-assistant-read-steps__content">
          {steps.map((step, i) => (
            <div key={i} className="brixlab-assistant-read-step">
              {steps.length > 1 && (
                <div className="brixlab-assistant-read-step__title">{getStepLabel(step)}</div>
              )}
              <pre className="brixlab-assistant-read-step__data">{step.result}</pre>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default ReadStepCard;
