<?php
namespace BrixlabAssistant\Assistant;

defined('ABSPATH') || exit;

/**
 * Singleton registry for AI assistant tools.
 *
 * Lazy-initialized: the extension hook fires on the first call to
 * get(), getAll(), or getToolDefinitions() — NOT at plugins_loaded.
 * This ensures all plugins have had a chance to add_action() before
 * the hook fires.
 *
 * Registration methods (pick one):
 *
 * 1. Global helper (simplest — no class needed):
 *    brixlab_assistant_register_tool([ 'name' => ..., 'preview' => ..., 'execute' => ... ]);
 *
 * 2. Hook + class (full control):
 *    add_action('brixlab_register_assistant_tools', function($registry) {
 *        $registry->register(new MyCustomTool());
 *    });
 *
 * 3. Hook + CallbackTool (inline, no class):
 *    add_action('brixlab_register_assistant_tools', function($registry) {
 *        $registry->register(new \BrixlabAssistant\Assistant\CallbackTool([...]));
 *    });
 */
class AssistantToolRegistry
{
    /** @var self|null */
    private static $instance = null;

    /** @var array<string, AbstractAssistantTool> */
    private $tools = [];

    /** @var bool */
    private $initialized = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
    }

    /**
     * Initialize the registry: register built-in tools and fire the extension hook.
     */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        $this->registerBuiltInTools();

        // Primary hook for the new assistant plugin
        do_action('brixlab_register_assistant_tools', $this);

        // Backward-compat hook (for existing integrations)
        do_action('brixte_register_assistant_tools', $this);
    }

    private function registerBuiltInTools(): void
    {
        $this->register(new Tools\UpdateOptionTool());
        $this->register(new Tools\ManagePluginTool());
        $this->register(new Tools\ManageMenuTool());
        $this->register(new Tools\ManagePostTool());
        $this->register(new Tools\ManageUserTool());
    }

    public function register(AbstractAssistantTool $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    public function get(string $name): ?AbstractAssistantTool
    {
        $this->ensureInitialized();
        return $this->tools[$name] ?? null;
    }

    public function getAll(): array
    {
        $this->ensureInitialized();
        return $this->tools;
    }

    public function getToolDefinitions(): array
    {
        $this->ensureInitialized();

        $definitions = [];
        foreach ($this->tools as $tool) {
            $definitions[] = $tool->toToolDefinition();
        }
        return $definitions;
    }

    private function ensureInitialized(): void
    {
        if (!$this->initialized) {
            $this->init();
        }
    }
}
