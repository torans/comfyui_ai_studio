<?php

namespace App\Services\Workflow;

use App\Models\WorkflowTemplate;

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
        $schema = $template->parameter_schema_json ?? [];

        // 遍历参数schema，动态替换工作流中的值
        foreach ($schema as $paramName => $config) {
            if (!isset($config['node']) || !isset($config['field'])) {
                continue;
            }

            $nodeId = $config['node'];
            $field = $config['field'];

            // 获取输入值，优先使用用户提供的，否则使用默认值
            $value = isset($inputs[$paramName]) ? $inputs[$paramName] : ($config['default'] ?? null);

            if ($value !== null) {
                // 类型转换逻辑
                $type = $config['type'] ?? 'string';
                if (($type === 'number' || $type === 'integer' || $type === 'int') && $paramName !== 'seed') {
                    // 非 seed 类数字进行 int 转换，seed 保持原样防止大数溢出
                    $value = (int) $value;
                } elseif ($type === 'float' || $type === 'decimal') {
                    $value = (float) $value;
                } elseif ($type === 'boolean' || $type === 'bool') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                // 强制注入
                \Illuminate\Support\Facades\Log::debug("Applying replacement: Node {$nodeId}, Field {$field} = " . json_encode($value));
                
                // 特殊处理 Seed：如果是默认值或 0，强制后端生成一个真正的随机大整数
                if ($paramName === 'seed' && ($value == 42 || $value == 0 || $value == 1023311306257007)) {
                    $value = mt_rand(1000000000, 999999999999999);
                    \Illuminate\Support\Facades\Log::info("Seed was default or missing, forced new random seed: {$value}");
                }
                
                $workflow[$nodeId]['inputs'][$field] = $value;
            }
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
}
