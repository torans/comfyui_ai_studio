<?php

namespace Database\Seeders;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;

/**
 * 工作流模板数据填充器
 * 从文件系统加载工作流 JSON 并配置动态参数
 */
class WorkflowTemplateSeeder extends Seeder
{
    /**
     * 运行数据库填充
     */
    public function run(): void
    {
        $workflows = $this->getWorkflowDefinitions();

        foreach ($workflows as $workflow) {
            WorkflowTemplate::updateOrCreate(
                ['code' => $workflow['code']],
                [
                    'name' => $workflow['name'],
                    'type' => $workflow['type'],
                    'version' => $workflow['version'],
                    'definition_json' => $workflow['definition_json'],
                    'parameter_schema_json' => $workflow['parameter_schema_json'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * 获取工作流定义列表
     *
     * @return array 工作流定义数组
     */
    private function getWorkflowDefinitions(): array
    {
        $basePath = base_path('../src/workflows');

        return [
            [
                // 文生图
                'code' => 't2i_default',
                'name' => '默认文生图',
                'type' => 't2i',
                'version' => '1.0.0',
                'definition_json' => $this->loadJsonFile($basePath . '/t2i_api.json'),
                'parameter_schema_json' => [
                    'prompt' => [
                        'node' => '69',
                        'field' => 'prompt',
                        'label' => '提示词',
                        'type' => 'textarea',
                        'default' => '一只可爱的蓝色机械猫',
                    ],
                    'width' => [
                        'node' => '64:13',
                        'field' => 'width',
                        'label' => '宽度',
                        'type' => 'number',
                        'default' => 1088,
                    ],
                    'height' => [
                        'node' => '64:13',
                        'field' => 'height',
                        'label' => '高度',
                        'type' => 'number',
                        'default' => 1088,
                    ],
                    'seed' => [
                        'node' => '64:3',
                        'field' => 'seed',
                        'label' => '随机种子',
                        'type' => 'number',
                        'default' => 42,
                    ],
                    'steps' => [
                        'node' => '64:3',
                        'field' => 'steps',
                        'label' => '采样步数',
                        'type' => 'number',
                        'default' => 8,
                    ],
                ],
            ],
            [
                // 图生图
                'code' => 'i2i_default',
                'name' => '默认图生图',
                'type' => 'i2i',
                'version' => '1.0.0',
                'definition_json' => $this->loadJsonFile($basePath . '/i2i_api.json'),
                'parameter_schema_json' => [
                    'prompt' => [
                        'node' => '6',
                        'field' => 'text',
                        'label' => '提示词',
                        'type' => 'textarea',
                        'default' => '',
                    ],
                    'strength' => [
                        'node' => '3',
                        'field' => 'denoise',
                        'label' => '变换强度',
                        'type' => 'number',
                        'default' => 0.7,
                    ],
                    'image' => [
                        'node' => '11',
                        'field' => 'image',
                        'label' => '输入图片',
                        'type' => 'image',
                        'default' => '',
                    ],
                    'seed' => [
                        'node' => '3',
                        'field' => 'seed',
                        'label' => '种子',
                        'type' => 'number',
                        'default' => 42,
                    ],
                ],
            ],
            [
                // 图生视频
                'code' => 'i2v_default',
                'name' => '默认图生视频',
                'type' => 'i2v',
                'version' => '1.0.0',
                'definition_json' => $this->loadJsonFile($basePath . '/video_ltx2_3_i2v.json'),
                'parameter_schema_json' => [
                    'prompt' => [
                        'node' => '5',
                        'field' => 'prompt',
                        'label' => '提示词',
                        'type' => 'textarea',
                        'default' => '',
                    ],
                    'image' => [
                        'node' => '6',
                        'field' => 'image',
                        'label' => '启动图片',
                        'type' => 'image',
                        'default' => '',
                    ],
                    'width' => [
                        'node' => '13',
                        'field' => 'width',
                        'label' => '宽度',
                        'type' => 'number',
                        'default' => 720,
                    ],
                    'height' => [
                        'node' => '13',
                        'field' => 'height',
                        'label' => '高度',
                        'type' => 'number',
                        'default' => 1280,
                    ],
                    'seed' => [
                        'node' => '14',
                        'field' => 'seed',
                        'label' => '随机种子',
                        'type' => 'number',
                        'default' => 42,
                    ],
                ],
            ],
            [
                // 文生视频
                'code' => 't2v_default',
                'name' => '默认文生视频',
                'type' => 't2v',
                'version' => '1.0.0',
                'definition_json' => $this->loadJsonFile($basePath . '/i2v_api.json'),
                'parameter_schema_json' => [
                    'prompt' => [
                        'node' => '5',
                        'field' => 'prompt',
                        'label' => '提示词',
                        'type' => 'textarea',
                        'default' => '',
                    ],
                ],
            ],
        ];
    }

    /**
     * 加载 JSON 文件
     *
     * @param string $path 文件路径
     * @return array JSON 内容
     */
    private function loadJsonFile(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        return json_decode(file_get_contents($path), true) ?? [];
    }
}
