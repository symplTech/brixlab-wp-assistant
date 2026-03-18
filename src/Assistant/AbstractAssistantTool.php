<?php
namespace BrixlabAssistant\Assistant;

defined('ABSPATH') || exit;

/**
 * Base class for AI assistant tools.
 *
 * Each tool exposes a name, description, and JSON Schema so the server-side
 * AI model can decide when and how to call it. The plugin executes the
 * tool locally in WordPress after user confirmation.
 *
 * To create a custom tool, extend this class and implement the five abstract methods,
 * then register it via the 'brixlab_register_assistant_tools' hook:
 *
 *   add_action('brixlab_register_assistant_tools', function($registry) {
 *       $registry->register(new MyCustomTool());
 *   });
 *
 * For simpler tools that don't need a full class, use CallbackTool instead:
 *
 *   brixlab_assistant_register_tool([
 *       'name'        => 'my_tool',
 *       'description' => 'Does something useful.',
 *       'parameters'  => [ 'type' => 'object', 'properties' => [...], 'required' => [...] ],
 *       'preview'     => function(array $params) { return ['title' => '...', 'changes' => [...]]; },
 *       'execute'     => function(array $params) { return ['success' => true, 'message' => '...']; },
 *   ]);
 */
abstract class AbstractAssistantTool
{
    /**
     * Machine-readable tool name (e.g. 'update_option', 'manage_post').
     * Must be unique across all registered tools. Use snake_case.
     */
    abstract public function getName(): string;

    /**
     * Human-readable description sent to the AI model so it knows when to use this tool.
     * Be specific about what the tool does and when it should be used.
     */
    abstract public function getDescription(): string;

    /**
     * JSON Schema describing the parameters the tool accepts.
     * This is sent to the AI model as the tool's input_schema.
     *
     * Example:
     *   return [
     *       'type'       => 'object',
     *       'properties' => [
     *           'title' => ['type' => 'string', 'description' => 'The page title.'],
     *       ],
     *       'required' => ['title'],
     *   ];
     */
    abstract public function getParameterSchema(): array;

    /**
     * Read-only preview of what the tool will do.
     * Called before execution so the user can confirm or reject the action.
     *
     * @param array $params Validated parameters from the AI model.
     * @return array {title: string, changes: array<{type: 'create'|'update'|'delete', label: string, from?: string, to?: string}>}
     */
    abstract public function preview(array $params): array;

    /**
     * Execute the tool (side effects).
     * Called only after the user confirms the preview — unless isReadOnly() returns true,
     * in which case the tool is auto-executed without user confirmation.
     *
     * @param array $params Validated parameters from the AI model.
     * @return array {success: bool, message: string, link?: {url: string, label: string}}
     */
    abstract public function execute(array $params): array;

    /**
     * Whether the given params represent a read-only (no side effects) call.
     *
     * Read-only calls are auto-executed without showing a preview card to the user.
     * The result is fed back to the AI so it can continue reasoning.
     *
     * Override this in your tool to return true for read actions (e.g. "list", "get").
     *
     * @param array $params The parameters from the AI model.
     * @return bool
     */
    public function isReadOnly(array $params): bool
    {
        return false;
    }

    /**
     * Build the tool definition array sent to the AI model.
     * You typically don't need to override this.
     */
    public function toToolDefinition(): array
    {
        return [
            'name'         => $this->getName(),
            'description'  => $this->getDescription(),
            'input_schema' => $this->getParameterSchema(),
        ];
    }
}
