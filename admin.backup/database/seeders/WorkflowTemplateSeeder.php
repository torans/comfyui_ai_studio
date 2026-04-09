<?php

namespace Database\Seeders;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Seeder;

class WorkflowTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $workflows = [
            [
                'name' => 'Default Text To Image',
                'code' => 't2i_default',
                'type' => 't2i',
                'version' => '1.0.0',
                'file' => base_path('../src/workflows/t2i_api.json'),
                'parameter_schema' => [
                    'prompt' => ['node' => '69', 'field' => 'prompt'],
                ],
            ],
            [
                'name' => 'Default Image To Image',
                'code' => 'i2i_default',
                'type' => 'i2i',
                'version' => '1.0.0',
                'file' => base_path('../src/workflows/i2i_api.json'),
                'parameter_schema' => [
                    'prompt' => ['node' => '6', 'field' => 'text'],
                ],
            ],
            [
                'name' => 'LTX 2.3 Image To Video',
                'code' => 'i2v_ltx2_3',
                'type' => 'i2v',
                'version' => '2.3.0',
                'file' => base_path('../src/workflows/video_ltx2_3_i2v.json'),
                'parameter_schema' => [
                    'prompt' => ['node' => '6', 'field' => 'prompt'],
                ],
            ],
        ];

        foreach ($workflows as $workflow) {
            if (!file_exists($workflow['file'])) {
                continue;
            }

            WorkflowTemplate::updateOrCreate(
                ['code' => $workflow['code']],
                [
                    'name' => $workflow['name'],
                    'type' => $workflow['type'],
                    'version' => $workflow['version'],
                    'definition_json' => json_decode(file_get_contents($workflow['file']), true),
                    'parameter_schema_json' => $workflow['parameter_schema'],
                    'is_active' => true,
                ],
            );
        }
    }
}
