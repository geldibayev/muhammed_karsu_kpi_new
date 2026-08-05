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
        $this->updateCriteria([
            'uz' => 'Ko‘pi bilan 3 ta o‘quv kontenti YouTube havolasi orqali taqdim etiladi. <br>Tasdiqlangan videodars maksimal ballning 50%, videorolik 40%, taqdimot 10% miqdorida baholanadi. Maksimal ball: chet tillari va jismoniy madaniyat yo‘nalishlari uchun 4 ball, ilmiy darajali uchun 3 ball, ilmiy darajasiz uchun 6 ball.',
            'kaa' => 'Kóbi menen 3 oqıw kontenti YouTube siltemesi arqalı usınıladı. <br>Tastıyıqlanǵan videosabaq maksimal balldıń 50%, videorolik 40%, prezentaciya 10% muǵdarında bahalanadı. Maksimal ball: shet tilleri hám dene mádeniyatı baǵdarları ushın 4 ball, ilimiy dárejeliler ushın 3 ball, ilimiy dárejesizler ushın 6 ball.',
            'ru' => 'Можно представить не более 3 учебных материалов посредством ссылки YouTube. <br>За подтвержденный видеоурок начисляется 50% максимального балла, за видеоролик — 40%, за презентацию — 10%. Максимум: 4 балла для иностранных языков и физической культуры, 3 балла для имеющих ученую степень, 6 баллов для не имеющих ученой степени.',
            'en' => 'Up to 3 educational resources may be submitted using YouTube links. <br>An approved video lesson receives 50% of the maximum score, a video clip 40%, and a presentation 10%. Maximum: 4 points for foreign languages and physical education, 3 points for academic degree holders, and 6 points for those without an academic degree.',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->updateCriteria([
            'uz' => 'O‘quv kontentlari “YouTube” havola kiritiladi. <br>Videodarslar-1,5 ball, videoroliklar-1 ball, taqdimotlar-0,5 ball.',
            'kaa' => 'Oqıw kontentleri "YouTube" siltemesi qosıladı. <br>Videosabaqlar-1,5 ball, videorolikler-1 ball, prezentaciyalar-0,5 ball.',
            'ru' => 'Учебный контент Введите ссылку на "YouTube." <br>Видеоуроки - 1,5 балла, видеоролики - 1 балл, презентации - 0,5 балла.',
            'en' => 'Educational content will be linked to "YouTube." <br>Video lessons - 1.5 points, video clips - 1 point, presentations - 0.5 points.',
        ]);
    }

    /** @param array<string, string> $translations */
    private function updateCriteria(array $translations): void
    {
        DB::table('criteria')
            ->where('code', '1.1')
            ->orderBy('id')
            ->eachById(function (object $criterion) use ($translations): void {
                $description = json_decode((string) $criterion->desc, true);
                $description = is_array($description) ? $description : [];

                foreach ($translations as $locale => $translation) {
                    $description[$locale] = $translation;
                }

                DB::table('criteria')->where('id', $criterion->id)->update([
                    'desc' => json_encode($description, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'updated_at' => now(),
                ]);
            }, column: 'id', alias: 'id');
    }
};
