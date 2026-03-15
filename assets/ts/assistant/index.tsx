import { createRoot } from 'react-dom/client';
import App from './App';

function mount() {
  if (document.getElementById('brixlab-assistant-root')) return;

  const container = document.createElement('div');
  container.id = 'brixlab-assistant-root';
  document.body.appendChild(container);

  const root = createRoot(container);
  root.render(<App />);
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', mount);
} else {
  mount();
}
