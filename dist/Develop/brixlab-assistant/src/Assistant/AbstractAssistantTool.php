<?php
namespace BrixlabAssistant\Assistant;

defined('ABSPATH') || exit;

/**
 * Base class for AI assistant tools.
 */
abstract class AbstractAssistantTool
{
    abstract public function getName(): string;
    abstract public function getDescription(): string;
    abstract public function getParameterSchema(): array;
    abstract public function preview(array $params): array;
    abstract public function execute(array $params): array;

    public function toToolDefinition(): array
    {
        return [
            'name'         => $this->getName(),
            'description'  => $this->getDescription(),
            'input_schema' => $this->getParameterSchema(),
        ];
    }
}
