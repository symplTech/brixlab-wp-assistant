<?php
/**
 * Tools Overview Page
 *
 * Displays all registered assistant tools so the admin can see
 * what capabilities are available to the AI.
 */
defined('ABSPATH') || exit;

$registry = \BrixlabAssistant\Assistant\AssistantToolRegistry::instance();
$tools = $registry->getAll();
$toolCount = count($tools);
?>

<style>
.brixlab-tools-wrap {
    max-width: 900px;
}
.brixlab-tools-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 16px;
    margin-top: 20px;
}
.brixlab-tool-card {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    padding: 20px;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
    transition: border-color 150ms ease, box-shadow 150ms ease;
}
.brixlab-tool-card:hover {
    border-color: #2271b1;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
}
.brixlab-tool-card__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}
.brixlab-tool-card__name {
    font-size: 14px;
    font-weight: 600;
    color: #1d2327;
    font-family: monospace;
    background: #f0f0f1;
    padding: 3px 8px;
    border-radius: 4px;
}
.brixlab-tool-card__badge {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 2px 8px;
    border-radius: 3px;
    color: #fff;
}
.brixlab-tool-card__badge--builtin {
    background: #2271b1;
}
.brixlab-tool-card__badge--extension {
    background: #00a32a;
}
.brixlab-tool-card__description {
    font-size: 13px;
    color: #50575e;
    line-height: 1.6;
    margin: 0 0 14px;
}
.brixlab-tool-card__params {
    border-top: 1px solid #f0f0f1;
    padding-top: 12px;
}
.brixlab-tool-card__params-title {
    font-size: 12px;
    font-weight: 600;
    color: #1d2327;
    margin: 0 0 8px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}
.brixlab-tool-card__param {
    display: flex;
    align-items: baseline;
    gap: 6px;
    font-size: 12px;
    margin-bottom: 4px;
    line-height: 1.5;
}
.brixlab-tool-card__param-name {
    font-family: monospace;
    font-weight: 600;
    color: #1d2327;
    white-space: nowrap;
}
.brixlab-tool-card__param-type {
    color: #8c8f94;
    font-family: monospace;
    white-space: nowrap;
}
.brixlab-tool-card__param-desc {
    color: #646970;
}
.brixlab-tool-card__param-required {
    color: #d63638;
    font-size: 11px;
    font-weight: 600;
}
.brixlab-tools-count {
    color: #50575e;
    font-size: 14px;
    margin: 5px 0 0;
}
.brixlab-tools-empty {
    background: #fff;
    border: 1px solid #c3c4c7;
    border-radius: 6px;
    padding: 40px;
    text-align: center;
    color: #646970;
    margin-top: 20px;
}
</style>

<div class="wrap brixlab-tools-wrap">
    <h1><?php esc_html_e('Assistant Tools', 'brixlab-assistant'); ?></h1>
    <p class="brixlab-tools-count">
        <?php
        printf(
            /* translators: %d: number of registered tools */
            esc_html(_n('%d tool registered', '%d tools registered', $toolCount, 'brixlab-assistant')),
            $toolCount
        );
        ?>
    </p>

    <?php if (empty($tools)) : ?>
        <div class="brixlab-tools-empty">
            <p><?php esc_html_e('No tools are currently registered. Tools are added by the assistant and other plugins.', 'brixlab-assistant'); ?></p>
        </div>
    <?php else : ?>
        <?php
        // Determine which tools are built-in vs extensions
        $builtinNames = ['update_option', 'manage_plugin', 'manage_menu', 'manage_post', 'manage_user'];
        ?>
        <div class="brixlab-tools-grid">
            <?php foreach ($tools as $tool) :
                $name = $tool->getName();
                $isBuiltin = in_array($name, $builtinNames, true);
                $schema = $tool->getParameterSchema();
                $properties = isset($schema['properties']) ? $schema['properties'] : [];
                $required = isset($schema['required']) ? $schema['required'] : [];
            ?>
                <div class="brixlab-tool-card">
                    <div class="brixlab-tool-card__header">
                        <span class="brixlab-tool-card__name"><?php echo esc_html($name); ?></span>
                        <span class="brixlab-tool-card__badge <?php echo esc_attr($isBuiltin ? 'brixlab-tool-card__badge--builtin' : 'brixlab-tool-card__badge--extension'); ?>">
                            <?php echo esc_html($isBuiltin ? __('Built-in', 'brixlab-assistant') : __('Extension', 'brixlab-assistant')); ?>
                        </span>
                    </div>

                    <p class="brixlab-tool-card__description"><?php echo esc_html($tool->getDescription()); ?></p>

                    <?php if (!empty($properties)) : ?>
                        <div class="brixlab-tool-card__params">
                            <p class="brixlab-tool-card__params-title"><?php esc_html_e('Parameters', 'brixlab-assistant'); ?></p>
                            <?php foreach ($properties as $paramName => $paramDef) :
                                $paramType = isset($paramDef['type']) ? $paramDef['type'] : 'mixed';
                                $paramDesc = isset($paramDef['description']) ? $paramDef['description'] : '';
                                $isRequired = in_array($paramName, $required, true);
                            ?>
                                <div class="brixlab-tool-card__param">
                                    <span class="brixlab-tool-card__param-name"><?php echo esc_html($paramName); ?></span>
                                    <span class="brixlab-tool-card__param-type"><?php echo esc_html($paramType); ?></span>
                                    <?php if ($isRequired) : ?>
                                        <span class="brixlab-tool-card__param-required"><?php esc_html_e('required', 'brixlab-assistant'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($paramDesc) : ?>
                                        <span class="brixlab-tool-card__param-desc">— <?php echo esc_html($paramDesc); ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
