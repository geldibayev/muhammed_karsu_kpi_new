<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->replacePromptText(
            '"point" qismiga 1 yozing',
            '"point" qismiga 3 yozing',
            '<raqam: 1 yoki 0>',
            '<raqam: 3 yoki 0>',
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->replacePromptText(
            '"point" qismiga 3 yozing',
            '"point" qismiga 1 yozing',
            '<raqam: 3 yoki 0>',
            '<raqam: 1 yoki 0>',
        );
    }

    private function replacePromptText(
        string $currentInstruction,
        string $newInstruction,
        string $currentSchema,
        string $newSchema,
    ): void {
        DB::transaction(function () use (
            $currentInstruction,
            $newInstruction,
            $currentSchema,
            $newSchema,
        ): void {
            $reportIds = DB::table('reports')->where('status', '1')->pluck('id');

            DB::table('criteria')
                ->whereIn('report_id', $reportIds)
                ->where('code', '3.1.12')
                ->whereNotNull('ai_prompt')
                ->orderBy('id')
                ->get(['id', 'ai_prompt'])
                ->each(function (object $criterion) use (
                    $currentInstruction,
                    $newInstruction,
                    $currentSchema,
                    $newSchema,
                ): void {
                    $prompt = str_replace(
                        [$currentInstruction, $currentSchema],
                        [$newInstruction, $newSchema],
                        $criterion->ai_prompt,
                    );

                    DB::table('criteria')->where('id', $criterion->id)->update([
                        'ai_prompt' => $prompt,
                        'updated_at' => now(),
                    ]);
                });
        }, 3);
    }
};
