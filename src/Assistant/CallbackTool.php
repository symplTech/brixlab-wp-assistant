<?php
namespace BrixlabAssistant\Assistant;

defined('ABSPATH') || exit;

/**
 * A convenience tool implementation that uses callbacks instead of requiring
 * developers to create a full class extending AbstractAssistantTool.
 *
 * Usage via the global helper:
 *
 *   brixlab_assistant_register_tool([
 *       'name'        => 'send_notification',
 *       'description' => 'Send an email notification to the site admin.',
 *       'parameters'  => [
 *           'type'       => 'object',
 *           'properties' => [
 *               'subject' => ['type' => 'string', 'description' => 'Email subject line.'],
 *               'message' => ['type' => 'string', 'description' => 'Email body text.'],
 *           ],
 *           'required' => ['subject', 'message'],
 *       ],
 *       'preview' => function(array $params) {
 *           return [
 *               'title'   => 'Send notification',
 *               'changes' => [
 *                   ['type' => 'create', 'label' => 'Email', 'to' => $params['subject']],
 *               ],
 *           ];
 *       },
 *       'execute' => function(array $params) {
 *           wp_mail(get_option('admin_email'), $params['subject'], $params['message']);
 *           return ['success' => true, 'message' => 'Notification sent.'];
 *       },
 *   ]);
 *
 * Or register directly via the hook:
 *
 *   add_action('brixlab_register_assistant_tools', function($registry) {
 *       $registry->register(new \BrixlabAssistant\Assistant\CallbackTool([...]));
 *   });
 */
class CallbackTool extends AbstractAssistantTool
{
    /** @var string */
    private $name;

    /** @var string */
    private $description;

    /** @var array */
    private $parameterSchema;

    /** @var callable */
    private $previewCallback;

    /** @var callable */
    private $executeCallback;

    /** @var callable|null */
    private $isReadOnlyCallback;

    /**
     * @param array $config {
     *   @type string        $name          Machine-readable tool name (snake_case).
     *   @type string        $description   Description for the AI model.
     *   @type array         $parameters    JSON Schema for tool parameters.
     *   @type callable      $preview       function(array $params): array  — returns {title, changes[]}.
     *   @type callable      $execute       function(array $params): array  — returns {success, message, link?}.
     *   @type callable|null $is_read_only  function(array $params): bool   — returns true for read-only calls (optional).
     * }
     */
    public function __construct(array $config)
    {
        if (empty($config['name']) || empty($config['description']) || empty($config['parameters'])) {
            throw new \InvalidArgumentException('CallbackTool requires name, description, and parameters.');
        }
        if (!is_callable($config['preview'] ?? null) || !is_callable($config['execute'] ?? null)) {
            throw new \InvalidArgumentException('CallbackTool requires callable preview and execute.');
        }

        $this->name                = $config['name'];
        $this->description         = $config['description'];
        $this->parameterSchema     = $config['parameters'];
        $this->previewCallback     = $config['preview'];
        $this->executeCallback     = $config['execute'];
        $this->isReadOnlyCallback  = isset($config['is_read_only']) && is_callable($config['is_read_only']) ? $config['is_read_only'] : null;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getParameterSchema(): array
    {
        return $this->parameterSchema;
    }

    public function preview(array $params): array
    {
        return call_user_func($this->previewCallback, $params);
    }

    public function execute(array $params): array
    {
        return call_user_func($this->executeCallback, $params);
    }

    public function isReadOnly(array $params): bool
    {
        if ($this->isReadOnlyCallback) {
            return (bool) call_user_func($this->isReadOnlyCallback, $params);
        }
        return false;
    }
}
