export interface BrixlabAssistantSettings {
  ajax_url: string;
  nonce: string;
  site_url: string;
}

declare global {
  interface Window {
    brixlabAssistant?: BrixlabAssistantSettings;
  }
}

export function getConfig(): BrixlabAssistantSettings {
  return window.brixlabAssistant || ({} as BrixlabAssistantSettings);
}
