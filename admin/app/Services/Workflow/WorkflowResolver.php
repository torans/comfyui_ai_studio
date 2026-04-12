<?php

namespace App\Services\Workflow;

use App\Models\WorkflowTemplate;
use Illuminate\Support\Facades\Log;

/**
 * 工作流解析器
 * 根据 parameter_schema_json 动态注入变量到工作流 JSON
 */
class WorkflowResolver
{
    /**
     * 解析工作流模板，用输入参数替换动态变量
     *
     * @param WorkflowTemplate $template 工作流模板
     * @param array $inputs 输入参数
     * @return array 解析后的工作流 JSON
     */
    public function resolve(WorkflowTemplate $template, array $inputs): array
    {
        $workflow = $template->definition_json;
        $schema = $this->normalizeSchema($template->parameter_schema_json ?? []);

        foreach ($schema as $index => $config) {
            $paramName = $config['field'] ?? null;
            $inputKey = $this->resolveInputKey($config, $index);

            if (! is_string($paramName) || $paramName === '') {
                continue;
            }

            if (($config['type'] ?? null) === 'select' && $paramName === 'aspect_ratio') {
                $this->applyAspectRatioPreset($workflow, $inputs, $config);
                continue;
            }

            if (! isset($config['node']) || ! isset($config['field'])) {
                continue;
            }

            $nodeId = $config['node'];
            $field = $config['field'];

            $value = array_key_exists($inputKey, $inputs)
                ? $inputs[$inputKey]
                : (array_key_exists($paramName, $inputs) ? $inputs[$paramName] : ($config['default'] ?? null));

            if ($value === null) {
                continue;
            }

            $type = $config['type'] ?? 'string';
            if (($type === 'number' || $type === 'integer' || $type === 'int') && $paramName !== 'seed') {
                $value = (int) $value;
            } elseif ($type === 'float' || $type === 'decimal') {
                $value = (float) $value;
            } elseif ($type === 'boolean' || $type === 'bool') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }

            Log::debug("Applying replacement: Node {$nodeId}, Field {$field} = " . json_encode($value));

            if ($paramName === 'seed' && ($value == 42 || $value == 0 || $value == 1023311306257007)) {
                $value = mt_rand(1000000000, 999999999999999);
                Log::info("Seed was default or missing, forced new random seed: {$value}");
            }

            $workflow[$nodeId]['inputs'][$field] = $value;
        }

        return $workflow;
    }

    /**
     * 获取工作流模板需要的参数列表
     *
     * @param WorkflowTemplate $template 工作流模板
     * @return array 参数列表
     */
    public function getParameters(WorkflowTemplate $template): array
    {
        return $template->parameter_schema_json ?? [];
    }

    private function resolveInputKey(array $config, int $index): string
    {
        $explicitKey = $config['input_key'] ?? null;
        if (is_string($explicitKey) && $explicitKey !== '') {
            return $explicitKey;
        }

        $field = $config['field'] ?? 'param';
        $node = $config['node'] ?? null;

        if (is_scalar($node) && $node !== '') {
            return "{$field}__{$node}";
        }

        return is_string($field) && $field !== '' ? $field : "param__{$index}";
    }

    private function normalizeSchema(mixed $schema): array
    {
        if (is_string($schema) && $schema !== '') {
            $decoded = json_decode($schema, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $this->normalizeSchema($decoded);
            }

            return [];
        }

        if (! is_array($schema)) {
            return [];
        }

        if (isset($schema['nodes']) && is_array($schema['nodes'])) {
            return array_values(array_filter($schema['nodes'], fn ($item) => is_array($item)));
        }

        if (array_is_list($schema)) {
            return array_values(array_filter($schema, fn ($item) => is_array($item)));
        }

        $normalized = [];
        foreach ($schema as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            if (! isset($config['input_key']) && is_string($key) && $key !== '') {
                $config['input_key'] = $key;
            }

            if (! isset($config['field']) && is_string($key)) {
                $config['field'] = $key;
            }

            $normalized[] = $config;
        }

        return $normalized;
    }

    private function applyAspectRatioPreset(array &$workflow, array $inputs, array $config): void
    {
        $selectedRatio = $inputs['aspect_ratio'] ?? ($config['default'] ?? null);
        if (! $selectedRatio) {
            return;
        }

        $preset = $config['presets'][$selectedRatio] ?? null;
        $targets = $config['targets'] ?? [];

        if (! $preset || ! $targets) {
            return;
        }

        foreach (['width', 'height'] as $dimension) {
            $target = $targets[$dimension] ?? null;
            $value = $preset[$dimension] ?? null;

            if (! $target || $value === null) {
                continue;
            }

            $nodeId = $target['node'] ?? null;
            $field = $target['field'] ?? null;

            if (! $nodeId || ! $field) {
                continue;
            }

            Log::debug("Applying aspect ratio preset: Node {$nodeId}, Field {$field} = {$value}");
            $workflow[$nodeId]['inputs'][$field] = (int) $value;
        }
    }
}
