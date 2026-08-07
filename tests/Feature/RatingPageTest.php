<?php

namespace Tests\Feature;

use App\Models\AcademicDegree;
use App\Models\AcademicRank;
use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionReviewerAssignment;
use App\Models\Datum;
use App\Models\DatumHistory;
use App\Models\Department;
use App\Models\EmployeeStatus;
use App\Models\EmployeeType;
use App\Models\EmploymentForm;
use App\Models\EmploymentStaff;
use App\Models\Evaluation;
use App\Models\Formula;
use App\Models\Point;
use App\Models\Report;
use App\Models\StaffPosition;
use App\Models\User;
use App\Models\Workplace;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class RatingPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $referenceId = 1000;

    public function test_guest_and_user_without_a_known_role_cannot_view_ratings(): void
    {
        $ratedUser = User::factory()->create();

        $this->get(route('ratings.index'))
            ->assertRedirect(route('login'));
        $this->get(route('ratings.show', $ratedUser))
            ->assertRedirect(route('login'));

        $user = User::factory()->withRole('unknown')->create();

        $this->actingAs($user)
            ->get(route('ratings.index'))
            ->assertForbidden();
        $this->actingAs($user)
            ->get(route('ratings.show', $ratedUser))
            ->assertForbidden();
    }

    public function test_guest_can_view_searchable_ratings_on_login_page_without_detail_actions(): void
    {
        $matchingUser = User::factory()->create([
            'name' => $this->userName('Ommaviy Reyting'),
            'degree' => 'hold_degrees',
            'image' => json_encode(
                ['min' => 'https://hemis.example/unavailable-avatar.jpg'],
                JSON_THROW_ON_ERROR,
            ),
        ]);
        $otherUser = User::factory()->create([
            'name' => $this->userName('Boshqa O‘qituvchi'),
            'degree' => 'hold_degrees',
        ]);
        $department = $this->createDepartment('Ommaviy reyting kafedrasi');
        $this->createWorkplace($matchingUser, $department, 'Professor');
        $this->createWorkplace($otherUser, $department, 'Dotsent');

        $response = $this->get(route('login', ['search' => 'ommaviy']));

        $response
            ->assertOk()
            ->assertSee('KPI KarSU')
            ->assertSee('Kirish')
            ->assertSee(route('login.user'))
            ->assertSee('Ommaviy Reyting')
            ->assertSee('https://hemis.example/unavailable-avatar.jpg')
            ->assertSee('data-rating-avatar-image', false)
            ->assertSee('data-rating-avatar-fallback', false)
            ->assertSee('fas fa-user fa-lg', false)
            ->assertSee(asset('dist/js/rating-avatar-fallback.js'))
            ->assertDontSee('Boshqa O‘qituvchi')
            ->assertDontSee('Excelga yuklash')
            ->assertDontSee('Ko‘rish')
            ->assertDontSee(route('ratings.show', $matchingUser));

        $this->assertGuest();
        $this->get(route('ratings.show', $otherUser))
            ->assertRedirect(route('login'));
    }

    public function test_users_are_ranked_by_active_report_and_show_hemis_workplace_data(): void
    {
        $viewer = User::factory()->create();
        $firstUser = User::factory()->create([
            'name' => $this->userName('Birinchi Ustoz'),
            'degree' => 'hold_degrees',
            'image' => json_encode(['min' => 'https://hemis.example/first.jpg'], JSON_THROW_ON_ERROR),
        ]);
        $secondUser = User::factory()->create([
            'name' => $this->userName('Ikkinchi Ustoz'),
            'degree' => 'hold_degrees',
        ]);
        $zeroPointUser = User::factory()->create([
            'name' => $this->userName('<script>alert(1)</script>'),
            'degree' => 'hold_degrees',
        ]);
        $withoutDegreeUser = User::factory()->create([
            'name' => $this->userName('Darajasiz Ustoz'),
            'degree' => 'no_degrees',
        ]);

        $mathematicsFaculty = $this->createDepartment('Matematika fakulteti');
        $algebraDepartment = $this->createDepartment('Algebra kafedrasi', $mathematicsFaculty);
        $this->createWorkplace($firstUser, $algebraDepartment, 'Dotsent');
        $this->createWorkplace($secondUser, $algebraDepartment, 'Assistent');
        $this->createWorkplace($zeroPointUser, $algebraDepartment, 'O‘qituvchi');
        $this->createWorkplace($withoutDegreeUser, $algebraDepartment, 'O‘qituvchi');

        $activeReport = $this->createReport('Faol hisobot', '1');
        $oldReport = $this->createReport('Eski hisobot', '2');
        $firstCriterion = $this->createCriterion($activeReport, 'Birinchi mezon');
        $secondCriterion = $this->createCriterion($activeReport, 'Ikkinchi mezon');
        $inactiveCriterion = $this->createCriterion($activeReport, 'Faol bo‘lmagan mezon', ['status' => '0']);
        $oldCriterion = $this->createCriterion($oldReport, 'Eski mezon');

        $this->createPoint($firstUser, $firstCriterion, $activeReport, 7.5);
        $this->createPoint($firstUser, $secondCriterion, $activeReport, 4.5);
        $this->createPoint($secondUser, $firstCriterion, $activeReport, 5);
        $this->createPoint($secondUser, $oldCriterion, $oldReport, 100);
        $this->createPoint($withoutDegreeUser, $firstCriterion, $activeReport, 50);
        $this->createPoint($firstUser, $inactiveCriterion, $activeReport, 100);

        $response = $this->actingAs($viewer)->get(route('ratings.index'));

        $response
            ->assertOk()
            ->assertSee('Ilmiy darajaga ega')
            ->assertSee('Ilmiy darajaga ega emas')
            ->assertSee('Matematika fakulteti')
            ->assertSee('Algebra kafedrasi')
            ->assertSee('Dotsent')
            ->assertSee('https://hemis.example/first.jpg')
            ->assertSee('data-rating-avatar-image', false)
            ->assertSee('data-rating-avatar-fallback', false)
            ->assertSee('d-inline-flex', false)
            ->assertSee(asset('dist/js/rating-avatar-fallback.js'))
            ->assertSee('12.00')
            ->assertSee('5.00')
            ->assertSee(route('ratings.show', $firstUser))
            ->assertDontSee('Darajasiz Ustoz')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSeeInOrder(['Birinchi Ustoz', 'Ikkinchi Ustoz'])
            ->assertViewHas('users', function (LengthAwarePaginator $users) use ($firstUser, $secondUser, $zeroPointUser): bool {
                return $users->total() === 3
                    && $users->items()[0]->is($firstUser)
                    && (float) $users->items()[0]->total_points === 12.0
                    && $users->items()[1]->is($secondUser)
                    && (float) $users->items()[1]->total_points === 5.0
                    && $users->getCollection()->contains(fn (User $user): bool => $user->is($zeroPointUser));
            });

        $this->actingAs($viewer)
            ->get(route('ratings.show', $firstUser))
            ->assertOk()
            ->assertViewHas('totalPoints', fn (mixed $totalPoints): bool => (float) $totalPoints === 12.0);

        $this->actingAs($firstUser)
            ->get(route('files.show', 'accepted'))
            ->assertOk()
            ->assertViewHas('totalPoints', fn (mixed $totalPoints): bool => (float) $totalPoints === 12.0)
            ->assertSee('Yakuniy jami ball: 12.00');

        $this->actingAs($firstUser)
            ->get(route('home'))
            ->assertOk()
            ->assertViewHas('points', fn (Collection $points): bool => (float) $points->sum() === 12.0);

        $this->actingAs($viewer)
            ->get(route('ratings.index', ['degree_group' => 'without_degree']))
            ->assertOk()
            ->assertSee('Darajasiz Ustoz')
            ->assertSee('50.00')
            ->assertDontSee('Birinchi Ustoz');
    }

    public function test_search_faculty_and_department_filters_can_be_combined(): void
    {
        $viewer = User::factory()->create();
        $firstFaculty = $this->createDepartment('Birinchi fakultet');
        $firstDepartment = $this->createDepartment('Birinchi kafedra', $firstFaculty);
        $secondFaculty = $this->createDepartment('Ikkinchi fakultet');
        $secondDepartment = $this->createDepartment('Ikkinchi kafedra', $secondFaculty);
        $matchingUser = User::factory()->create([
            'name' => $this->userName('Qidirilgan Olim'),
            'degree' => 'hold_degrees',
        ]);
        $otherUser = User::factory()->create([
            'name' => $this->userName('Boshqa Olim'),
            'degree' => 'hold_degrees',
        ]);
        $surnameUser = User::factory()->create([
            'name' => [
                'full' => 'Begench Yegendurdiyevich',
                'first' => 'Begench',
                'last' => 'GELDIBAYEV',
                'third' => 'Yegendurdiyevich',
                'short' => 'GELDIBAYEV B.Y.',
            ],
            'degree' => 'hold_degrees',
        ]);
        $this->createWorkplace($matchingUser, $firstDepartment, 'Professor');
        $this->createWorkplace($otherUser, $secondDepartment, 'Assistent');
        $this->createWorkplace($surnameUser, $firstDepartment, 'Dotsent');

        $response = $this->actingAs($viewer)->get(route('ratings.index', [
            'search' => '  Qidirilgan   Olim ',
            'degree_group' => 'with_degree',
            'faculty' => $firstFaculty->getKey(),
            'department' => $firstDepartment->getKey(),
        ]));

        $response
            ->assertOk()
            ->assertSee('Qidirilgan Olim')
            ->assertSee('Birinchi fakultet')
            ->assertSee('Birinchi kafedra')
            ->assertSee('Professor')
            ->assertDontSee('Boshqa Olim')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1);

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'search' => 'geldibayev begench',
                'degree_group' => 'with_degree',
                'faculty' => $firstFaculty->getKey(),
                'department' => $firstDepartment->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Begench Yegendurdiyevich')
            ->assertSee('Dotsent')
            ->assertDontSee('Qidirilgan Olim')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1);
    }

    public function test_invalid_rating_filters_are_rejected(): void
    {
        $viewer = User::factory()->create();

        $this->actingAs($viewer)
            ->get(route('ratings.index', ['degree_group' => 'invalid']))
            ->assertSessionHasErrors('degree_group');

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'all_users',
                'resource_status' => 'invalid',
            ]))
            ->assertSessionHasErrors('resource_status');
    }

    public function test_all_users_tab_lists_and_filters_users_by_current_report_resource_status(): void
    {
        $viewer = User::factory()->create();
        $faculty = $this->createDepartment('Jami foydalanuvchilar fakulteti');
        $department = $this->createDepartment('Jami foydalanuvchilar kafedrasi', $faculty);
        $uploadedUser = User::factory()->create([
            'name' => $this->userName('Resurs Yuklagan Ustoz'),
            'degree' => 'hold_degrees',
        ]);
        $notUploadedUser = User::factory()->create([
            'name' => $this->userName('Resurs Yuklamagan Ustoz'),
            'degree' => 'no_degrees',
        ]);
        $this->createWorkplace($uploadedUser, $department, 'Professor');
        $this->createWorkplace($notUploadedUser, $department, 'Assistent');
        $activeReport = $this->createReport('Joriy jami foydalanuvchilar hisoboti', '1');
        $oldReport = $this->createReport('Eski jami foydalanuvchilar hisoboti', '0');
        $criterion = $this->createCriterion($activeReport, 'Joriy resurs mezoni');
        $oldCriterion = $this->createCriterion($oldReport, 'Eski resurs mezoni');
        $this->createAcceptedDatum($uploadedUser, $criterion, 1, 'Birinchi joriy resurs');
        $this->createPendingDatum($uploadedUser, $criterion, 'checking');
        $this->createAcceptedDatum($notUploadedUser, $oldCriterion, 1, 'Faqat eski resurs');

        $response = $this->actingAs($viewer)->get(route('ratings.index', [
            'mode' => 'all_users',
            'faculty' => $faculty->id,
            'department' => $department->id,
        ]));

        $response
            ->assertOk()
            ->assertSee('Jami foydalanuvchilar')
            ->assertSee('Resurs Yuklagan Ustoz')
            ->assertSee('Resurs Yuklamagan Ustoz')
            ->assertSee('Resurs yuklagan')
            ->assertSee('Resurs yuklamagan')
            ->assertSee('Yuklagan resurslari')
            ->assertSee('name="resource_status"', false)
            ->assertViewHas('users', function (LengthAwarePaginator $users) use ($uploadedUser, $notUploadedUser): bool {
                $usersById = $users->getCollection()->keyBy('id');

                return $users->total() === 2
                    && (int) $usersById->get($uploadedUser->id)?->uploaded_resources_count === 2
                    && (int) $usersById->get($notUploadedUser->id)?->uploaded_resources_count === 0;
            });

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'all_users',
                'resource_status' => 'not_uploaded',
                'faculty' => $faculty->id,
            ]))
            ->assertOk()
            ->assertSee('Resurs Yuklamagan Ustoz')
            ->assertDontSee('Resurs Yuklagan Ustoz')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 1);
    }

    public function test_all_users_excel_contains_resource_status_and_autofilter(): void
    {
        $viewer = User::factory()->create();
        $faculty = $this->createDepartment('Excel jami fakulteti');
        $department = $this->createDepartment('Excel jami kafedrasi', $faculty);
        $uploadedUser = User::factory()->create(['name' => $this->userName('Excel Yuklagan')]);
        $notUploadedUser = User::factory()->create(['name' => $this->userName('Excel Yuklamagan')]);
        $this->createWorkplace($uploadedUser, $department, 'Professor');
        $this->createWorkplace($notUploadedUser, $department, 'Assistent');
        $report = $this->createReport('Excel faol hisoboti', '1');
        $criterion = $this->createCriterion($report, 'Excel resurs mezoni');
        $this->createAcceptedDatum($uploadedUser, $criterion, 1);

        $response = $this->actingAs($viewer)->get(route('ratings.export', [
            'mode' => 'all_users',
            'faculty' => $faculty->id,
            'department' => $department->id,
        ]));

        $response->assertOk()->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($sheet);
        $this->assertStringContainsString('F.I.Sh.', $sheet);
        $this->assertStringContainsString('Fakultet', $sheet);
        $this->assertStringContainsString('Kafedra', $sheet);
        $this->assertStringContainsString('Yuklagan resurslari soni', $sheet);
        $this->assertStringContainsString('Resurs yuklagan', $sheet);
        $this->assertStringContainsString('Resurs yuklamagan', $sheet);
        $this->assertStringContainsString('Excel Yuklagan', $sheet);
        $this->assertStringContainsString('Excel Yuklamagan', $sheet);
        $this->assertStringContainsString('<autoFilter ref="A1:H3"/>', $sheet);
    }

    public function test_faculty_rankings_and_filters_exclude_non_faculty_root_units(): void
    {
        $viewer = User::factory()->create();
        $faculty = $this->createDepartment('Haqiqiy fakultet');
        $department = $this->createDepartment('Fakultet kafedrasi', $faculty);
        $administrativeDepartment = $this->createDepartment('Kadrlar bo‘limi');
        $registrarOffice = $this->createDepartment('Registrator ofis');
        $facultyUser = User::factory()->create();
        $administrativeUser = User::factory()->create();
        $registrarUser = User::factory()->create();
        $this->createWorkplace($facultyUser, $department, 'Dotsent');
        $this->createWorkplace($administrativeUser, $administrativeDepartment, 'Bo‘lim boshlig‘i');
        $this->createWorkplace($registrarUser, $registrarOffice, 'Registrator');
        $report = $this->createReport('Tuzilmalar hisoboti', '1');
        $criterion = $this->createCriterion($report, 'Umumiy mezon');
        $this->createPoint($facultyUser, $criterion, $report, 5);
        $this->createPoint($administrativeUser, $criterion, $report, 100);
        $this->createPoint($registrarUser, $criterion, $report, 200);

        $this->actingAs($viewer)
            ->get(route('ratings.index', ['mode' => 'faculties']))
            ->assertOk()
            ->assertSee('Haqiqiy fakultet')
            ->assertDontSee('Kadrlar bo‘limi')
            ->assertDontSee('Registrator ofis')
            ->assertViewHas(
                'faculties',
                fn (Collection $faculties): bool => $faculties->modelKeys() === [$faculty->getKey()],
            )
            ->assertViewHas(
                'unitRankings',
                fn (LengthAwarePaginator $rankings): bool => $rankings->total() === 1
                    && $rankings->items()[0]['id'] === $faculty->getKey(),
            );

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'faculties',
                'faculty' => $administrativeDepartment->getKey(),
            ]))
            ->assertSessionHasErrors('faculty');

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'faculties',
                'faculty' => $registrarOffice->getKey(),
            ]))
            ->assertSessionHasErrors('faculty');
    }

    public function test_faculty_and_department_modes_rank_units_and_show_internal_user_rankings(): void
    {
        $viewer = User::factory()->create();
        $firstFaculty = $this->createDepartment('Birinchi fakultet');
        $firstDepartment = $this->createDepartment('Birinchi kafedra', $firstFaculty);
        $secondFaculty = $this->createDepartment('Ikkinchi fakultet');
        $secondDepartment = $this->createDepartment('Ikkinchi kafedra', $secondFaculty);
        $firstUser = User::factory()->create([
            'name' => $this->userName('Birinchi Fakultet Yetakchisi'),
            'degree' => 'hold_degrees',
        ]);
        $secondUser = User::factory()->create([
            'name' => $this->userName('Birinchi Fakultet Ikkinchisi'),
            'degree' => 'no_degrees',
        ]);
        $thirdUser = User::factory()->create([
            'name' => $this->userName('Ikkinchi Fakultet Yetakchisi'),
            'degree' => 'hold_degrees',
        ]);
        $this->createWorkplace($firstUser, $firstDepartment, 'Professor');
        $this->createWorkplace($secondUser, $firstDepartment, 'Assistent');
        $this->createWorkplace($thirdUser, $secondDepartment, 'Dotsent');
        $report = $this->createReport('Tuzilmalar hisoboti', '1');
        $criterion = $this->createCriterion($report, 'Umumiy mezon');
        $this->createPoint($firstUser, $criterion, $report, 10);
        $this->createPoint($secondUser, $criterion, $report, 5);
        $this->createPoint($thirdUser, $criterion, $report, 10);

        $this->actingAs($viewer)
            ->get(route('ratings.index', ['mode' => 'faculties']))
            ->assertOk()
            ->assertSee('Fakultet bo‘yicha')
            ->assertSee('Kafedra bo‘yicha')
            ->assertSee('Fakultetlar reytingi')
            ->assertSee('Ichki reyting')
            ->assertSee('Excelga yuklash')
            ->assertViewHas('unitRankings', function (LengthAwarePaginator $rankings) use (
                $firstFaculty,
                $secondFaculty,
            ): bool {
                return $rankings->total() === 2
                    && $rankings->items()[0]['id'] === $secondFaculty->getKey()
                    && (float) $rankings->items()[0]['total_points'] === 10.0
                    && (float) $rankings->items()[0]['average_points'] === 10.0
                    && $rankings->items()[1]['id'] === $firstFaculty->getKey()
                    && (float) $rankings->items()[1]['total_points'] === 15.0
                    && (float) $rankings->items()[1]['average_points'] === 7.5
                    && $rankings->items()[1]['users_count'] === 2
                    && $rankings->items()[1]['with_degree_count'] === 1
                    && $rankings->items()[1]['without_degree_count'] === 1;
            });

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'faculties',
                'faculty' => $firstFaculty->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Birinchi fakultet ichki reytingi')
            ->assertSeeInOrder(['Birinchi Fakultet Yetakchisi', 'Birinchi Fakultet Ikkinchisi'])
            ->assertDontSee('Ikkinchi Fakultet Yetakchisi')
            ->assertViewHas('users', fn (LengthAwarePaginator $users): bool => $users->total() === 2);

        $this->actingAs($viewer)
            ->get(route('ratings.index', ['mode' => 'departments']))
            ->assertOk()
            ->assertViewHas(
                'unitRankings',
                fn (LengthAwarePaginator $rankings): bool => $rankings->total() === 2
                    && $rankings->items()[0]['id'] === $secondDepartment->getKey()
                    && (float) $rankings->items()[0]['total_points'] === 10.0
                    && (float) $rankings->items()[0]['average_points'] === 10.0
                    && $rankings->items()[1]['id'] === $firstDepartment->getKey()
                    && (float) $rankings->items()[1]['total_points'] === 15.0
                    && (float) $rankings->items()[1]['average_points'] === 7.5,
            );

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'departments',
                'faculty' => $firstFaculty->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Kafedralar reytingi')
            ->assertViewHas(
                'unitRankings',
                fn (LengthAwarePaginator $rankings): bool => $rankings->total() === 1
                    && $rankings->items()[0]['id'] === $firstDepartment->getKey()
                    && (float) $rankings->items()[0]['average_points'] === 7.5,
            );

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'departments',
                'faculty' => $firstFaculty->getKey(),
                'department' => $firstDepartment->getKey(),
            ]))
            ->assertOk()
            ->assertSee('Birinchi kafedra ichki reytingi')
            ->assertSee('Birinchi Fakultet Yetakchisi')
            ->assertSee('Birinchi Fakultet Ikkinchisi')
            ->assertDontSee('Ikkinchi Fakultet Yetakchisi');

        $this->actingAs($viewer)
            ->get(route('ratings.index', [
                'mode' => 'departments',
                'faculty' => $firstFaculty->getKey(),
                'department' => $secondDepartment->getKey(),
            ]))
            ->assertSessionHasErrors('department');
    }

    public function test_authorized_user_can_export_filtered_rating_as_real_xlsx(): void
    {
        $viewer = User::factory()->create();
        $faculty = $this->createDepartment('Eksport fakulteti');
        $department = $this->createDepartment('Eksport kafedrasi', $faculty);
        $firstUser = User::factory()->create([
            'name' => $this->userName('=2+2 Formula Emas'),
            'degree' => 'hold_degrees',
        ]);
        $secondUser = User::factory()->create([
            'name' => $this->userName('Oddiy Foydalanuvchi'),
            'degree' => 'hold_degrees',
        ]);
        $this->createWorkplace($firstUser, $department, 'Professor');
        $this->createWorkplace($secondUser, $department, 'Dotsent');
        $report = $this->createReport('Eksport hisoboti', '1');
        $criterion = $this->createCriterion($report, 'Eksport mezoni');
        $this->createPoint($firstUser, $criterion, $report, 12.5);
        $this->createPoint($secondUser, $criterion, $report, 7);

        $response = $this->actingAs($viewer)->get(route('ratings.export', [
            'mode' => 'with_degree',
            'faculty' => $faculty->getKey(),
        ]));

        $response
            ->assertOk()
            ->assertDownload()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );

        $path = $response->baseResponse->getFile()->getPathname();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($path) === true);
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        @unlink($path);

        $this->assertIsString($sheet);
        $this->assertStringContainsString('=2+2 Formula Emas', $sheet);
        $this->assertStringContainsString('Oddiy Foydalanuvchi', $sheet);
        $this->assertStringContainsString('12.5', $sheet);
        $this->assertStringNotContainsString('<f>', $sheet);

        $unitResponse = $this->actingAs($viewer)->get(route('ratings.export', [
            'mode' => 'faculties',
        ]));
        $unitResponse->assertOk()->assertDownload();
        $unitPath = $unitResponse->baseResponse->getFile()->getPathname();
        $unitZip = new ZipArchive;
        $this->assertTrue($unitZip->open($unitPath) === true);
        $unitSheet = $unitZip->getFromName('xl/worksheets/sheet1.xml');
        $unitZip->close();
        @unlink($unitPath);

        $this->assertIsString($unitSheet);
        $this->assertStringContainsString('Eksport fakulteti', $unitSheet);
        $this->assertStringContainsString('Xodimlar', $unitSheet);
        $this->assertStringContainsString('19.5', $unitSheet);

        Auth::logout();
        $this->get(route('ratings.export'))->assertRedirect(route('login'));

        $unknownRole = User::factory()->withRole('unknown')->create();
        $this->actingAs($unknownRole)
            ->get(route('ratings.export'))
            ->assertForbidden();
    }

    public function test_rating_details_show_criterion_scores_and_attributed_evaluators(): void
    {
        $competitionFormula = Formula::query()->firstOrCreate(
            ['code' => Formula::Competition],
            ['name' => ['uz' => 'Raqobat asosida'], 'status' => '1'],
        );
        $maximumFormula = Formula::query()->firstOrCreate(
            ['code' => Formula::Maximum],
            ['name' => ['uz' => 'Maksimal ballgacha'], 'status' => '1'],
        );
        $unlimitedFormula = Formula::query()->firstOrCreate(
            ['code' => Formula::Unlimited],
            ['name' => ['uz' => 'Cheklanmagan yig‘indi'], 'status' => '1'],
        );
        Evaluation::query()->firstOrCreate(
            ['code' => 'hold_degrees'],
            ['name' => ['uz' => 'Ilmiy darajali'], 'status' => '1'],
        );

        $viewer = User::factory()->create();
        $ratedUser = User::factory()->create([
            'name' => $this->userName('Baholangan Ustoz'),
            'degree' => 'hold_degrees',
        ]);
        $reviewer = User::factory()->create([
            'name' => $this->userName('Mas’ul Baholovchi'),
        ]);
        $activeReport = $this->createReport('Faol hisobot', '1');
        $oldReport = $this->createReport('Eski hisobot', '2');
        $firstSection = $this->createCriterion($activeReport, 'Birinchi bo‘lim');
        $secondSection = $this->createCriterion($activeReport, 'Ikkinchi bo‘lim');
        $oldSection = $this->createCriterion($oldReport, 'Eski bo‘lim');
        $manualCriterion = $this->createCriterion($activeReport, 'Qo‘lda baholangan kriteriya', [
            'checking' => 'manual',
            'parent_id' => $firstSection->getKey(),
        ]);
        $aiCriterion = $this->createCriterion($activeReport, 'AI baholagan kriteriya', [
            'checking' => 'ai',
            'ai_model' => 'gpt-test',
            'formula_id' => $competitionFormula->getKey(),
            'parent_id' => $firstSection->getKey(),
        ]);
        $systemCriterion = $this->createCriterion($activeReport, 'Auditsiz kriteriya', [
            'checking' => 'site:test',
            'formula_id' => $unlimitedFormula->getKey(),
            'parent_id' => $firstSection->getKey(),
        ]);
        $aiWithoutAuditCriterion = $this->createCriterion($activeReport, 'Auditsiz AI kriteriya', [
            'checking' => 'ai',
            'ai_model' => 'gpt-fallback',
            'formula_id' => $maximumFormula->getKey(),
            'parent_id' => $firstSection->getKey(),
        ]);
        $pendingCriterion = $this->createCriterion($activeReport, 'Baholanishi kutilayotgan kriteriya', [
            'checking' => 'manual',
            'parent_id' => $firstSection->getKey(),
        ]);
        $cancelledCriterion = $this->createCriterion($activeReport, 'Qaytarilgan kriteriya', [
            'parent_id' => $firstSection->getKey(),
        ]);
        $unuploadedCriterion = $this->createCriterion($activeReport, 'Yuklanmagan kriteriya', [
            'parent_id' => $secondSection->getKey(),
        ]);
        $hIndexCriterion = $this->createCriterion($activeReport, 'H-index kriteriyasi', [
            'code' => Criterion::H_INDEX_CODE,
            'formula_id' => $maximumFormula->getKey(),
            'parent_id' => $secondSection->getKey(),
        ]);
        $oldCriterion = $this->createCriterion($oldReport, 'Eski kriteriya', [
            'parent_id' => $oldSection->getKey(),
        ]);
        $inactiveCriterion = $this->createCriterion($activeReport, 'Faol bo‘lmagan kriteriya', [
            'parent_id' => $firstSection->getKey(),
            'status' => '0',
        ]);
        $inactiveSection = $this->createCriterion($activeReport, 'Faol bo‘lmagan bo‘lim', [
            'status' => '0',
        ]);
        $criterionUnderInactiveSection = $this->createCriterion($activeReport, 'Yashirin bo‘lim kriteriyasi', [
            'parent_id' => $inactiveSection->getKey(),
        ]);
        $oldPendingCriterion = $this->createCriterion($oldReport, 'Eski baholanmagan kriteriya', [
            'parent_id' => $oldSection->getKey(),
        ]);

        CriterionEvaluation::query()->create([
            'criterion_id' => $aiCriterion->getKey(),
            'evaluation' => 'hold_degrees',
            'has' => '1',
            'score' => 5,
        ]);

        $this->createPoint($ratedUser, $manualCriterion, $activeReport, 4.25);
        $this->createPoint($ratedUser, $aiCriterion, $activeReport, 3.5);
        $this->createPoint($ratedUser, $systemCriterion, $activeReport, 2);
        $this->createPoint($ratedUser, $aiWithoutAuditCriterion, $activeReport, 1.25);
        $this->createPoint($ratedUser, $inactiveCriterion, $activeReport, 100);
        $this->createPoint($ratedUser, $criterionUnderInactiveSection, $activeReport, 100);
        $this->createPoint($ratedUser, $oldCriterion, $oldReport, 99);

        $manualDatum = $this->createAcceptedDatum($ratedUser, $manualCriterion, 4.25);
        DatumHistory::query()->create([
            'datum_id' => $manualDatum->getKey(),
            'user_id' => $reviewer->getKey(),
            'type' => 'success',
            'message' => 'Mas’ul tasdiqladi.',
            'message_type' => 'manual_review_approved',
        ]);
        $aiDatum = $this->createAcceptedDatum($ratedUser, $aiCriterion, 3.5);
        DatumHistory::query()->create([
            'datum_id' => $aiDatum->getKey(),
            'user_id' => $ratedUser->getKey(),
            'type' => 'success',
            'message' => 'AI tasdiqladi.',
            'message_type' => 'ai_evaluation',
        ]);
        $additionalAiSubmissions = collect([1.10, 1.20, 1.30])
            ->map(fn (float $point): Datum => $this->createAcceptedDatum($ratedUser, $aiCriterion, $point));
        $receivedPendingDatum = $this->createPendingDatum($ratedUser, $pendingCriterion, 'received');
        $checkingPendingDatum = $this->createPendingDatum($ratedUser, $pendingCriterion, 'checking');
        $manualPendingDatum = $this->createPendingDatum($ratedUser, $manualCriterion, 'checking');
        $this->createPendingDatum($ratedUser, $oldPendingCriterion, 'checking');
        $cancelledDatum = $this->createPendingDatum($ratedUser, $cancelledCriterion, 'cancelled');

        $response = $this->actingAs($viewer)->get(route('ratings.show', [
            'user' => $ratedUser,
            'degree_group' => 'with_degree',
            'search' => 'Baholangan',
        ]));

        $response
            ->assertOk()
            ->assertSee('data-testid="rating-criterion-row"', false)
            ->assertSee('data-target="#accepted-submissions-'.$aiCriterion->getKey().'"', false)
            ->assertSee('4 ta tasdiqlangan')
            ->assertSee('data-target="#cancelled-submissions-'.$cancelledCriterion->getKey().'"', false)
            ->assertSee('btn btn-outline-danger btn-sm', false)
            ->assertSee('list-group-item list-group-item-danger', false)
            ->assertSee('1 ta qaytarilgan')
            ->assertSee(route('upload.details', $cancelledDatum))
            ->assertSee('Baholangan Ustoz')
            ->assertSee('Birinchi bo‘lim')
            ->assertSee('#1')
            ->assertSee("1/{$manualCriterion->getKey()}")
            ->assertSee('Qo‘lda baholangan kriteriya')
            ->assertSee('4.25')
            ->assertSee('Mas’ul Baholovchi')
            ->assertSee('AI baholagan kriteriya')
            ->assertSee('3.50')
            ->assertSee('data-testid="rating-method-button"', false)
            ->assertSee('data-target="#rating-method-'.$aiCriterion->getKey().'"', false)
            ->assertSee('Baholash usuli')
            ->assertSee('Raqobat asosida')
            ->assertSee('Maksimal ballgacha')
            ->assertSee('Cheklanmagan yig‘indi')
            ->assertSee('H-index bo‘yicha')
            ->assertSee('Faqat linki va H-index qiymati to‘liq kiritilgan platformalar hisobga olinadi')
            ->assertSee('data-target="#rating-method-'.$hIndexCriterion->getKey().'"', false)
            ->assertSee('Qanday hisoblanadi?')
            ->assertSee('Sizning toifangiz uchun maksimal ball')
            ->assertSee('Oddiy misol')
            ->assertSee('5.00 × 8 ÷ 10 = 4.00 ball')
            ->assertDontSee('xom')
            ->assertDontSee('Xom')
            ->assertSee('Sun’iy intellekt tomonidan baholangan')
            ->assertSee('Auditsiz kriteriya')
            ->assertSee('Auditda qayd etilmagan')
            ->assertSee('Auditsiz AI kriteriya')
            ->assertDontSee('gpt-test')
            ->assertDontSee('gpt-fallback')
            ->assertSee('Baholanishi kutilayotgan kriteriya')
            ->assertSee('Baholanmagan')
            ->assertSee('Baholash kutilmoqda')
            ->assertSee('2 ta baholanmagan yuklama')
            ->assertSee('1 ta baholanmagan yuklama')
            ->assertSee('data-target="#pending-submissions-'.$pendingCriterion->getKey().'"', false)
            ->assertSee('2 ta baholanmagan')
            ->assertSee(route('upload.details', $receivedPendingDatum))
            ->assertSee(route('upload.details', $checkingPendingDatum))
            ->assertSee(route('upload.details', $manualPendingDatum))
            ->assertSee('Qaytarilgan kriteriya')
            ->assertSee('Qaytarilgan')
            ->assertSee('Ikkinchi bo‘lim')
            ->assertSee('#2')
            ->assertSee("2/{$unuploadedCriterion->getKey()}")
            ->assertSee('Yuklanmagan kriteriya')
            ->assertSee('Yuklanmagan')
            ->assertSee('Ma’lumot yuklanmagan')
            ->assertSee('Jami: 11.00')
            ->assertDontSee('Faol bo‘lmagan kriteriya')
            ->assertDontSee('Yashirin bo‘lim kriteriyasi')
            ->assertDontSee('Eski kriteriya')
            ->assertDontSee('Eski baholanmagan kriteriya')
            ->assertDontSee('99.00')
            ->assertSee(route('ratings.index', [
                'search' => 'Baholangan',
                'degree_group' => 'with_degree',
            ]));

        $additionalAiSubmissions->each(
            fn (Datum $datum) => $response->assertSee(route('upload.details', $datum)),
        );

        $this->assertSame(1, substr_count($response->getContent(), 'Baholanishi kutilayotgan kriteriya'));

        $this->get(route('ratings.show', PHP_INT_MAX))->assertNotFound();
    }

    public function test_authenticated_user_can_view_and_download_another_users_rating_resources(): void
    {
        Storage::fake('local');

        $viewer = User::factory()->create();
        $ratedUser = User::factory()->create([
            'name' => $this->userName('Shaffof Reyting Ustozi'),
        ]);
        $activeReport = $this->createReport('Faol hisobot', '1');
        $oldReport = $this->createReport('Eski hisobot', '2');
        $section = $this->createCriterion($activeReport, 'Faol bo‘lim');
        $criterion = $this->createCriterion($activeReport, 'Shaffof mezon', [
            'parent_id' => $section->getKey(),
        ]);
        $oldSection = $this->createCriterion($oldReport, 'Eski bo‘lim');
        $oldCriterion = $this->createCriterion($oldReport, 'Eski mezon', [
            'parent_id' => $oldSection->getKey(),
        ]);
        $this->createPoint($ratedUser, $criterion, $activeReport, 6.75);

        $path = 'uploads/shaffof-resurs.pdf';
        Storage::disk('local')->put($path, 'accepted evidence');
        $acceptedDatum = Datum::query()->create([
            'name' => 'Shaffof ilmiy maqola.pdf',
            'material' => ['type' => 'file', 'disk' => 'local', 'path' => $path],
            'user_id' => $ratedUser->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => 6.75,
        ]);
        $cancelledPath = 'uploads/qaytarilgan-resurs.pdf';
        Storage::disk('local')->put($cancelledPath, 'cancelled evidence');
        $cancelledDatum = Datum::query()->create([
            'name' => 'Qaytarilgan ilmiy maqola.pdf',
            'material' => ['type' => 'file', 'disk' => 'local', 'path' => $cancelledPath],
            'user_id' => $ratedUser->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'cancelled',
            'point' => 0,
            'reason' => 'Talablarga mos kelmadi.',
        ]);
        $pendingDatum = $this->createPendingDatum($ratedUser, $criterion, 'checking');
        $pendingDatum->update(['name' => 'Yopiq tekshiruv resursi']);
        $oldDatum = $this->createAcceptedDatum($ratedUser, $oldCriterion, 99, 'Eski hisobot resursi');

        $this->actingAs($viewer)
            ->get(route('ratings.show', $ratedUser))
            ->assertOk()
            ->assertSee('#'.$acceptedDatum->id)
            ->assertDontSee('Shaffof ilmiy maqola.pdf')
            ->assertSee('6.75 ball')
            ->assertSee(route('upload.details', $acceptedDatum))
            ->assertSee('1 ta qaytarilgan')
            ->assertSee(route('upload.details', $cancelledDatum))
            ->assertDontSee('Yopiq tekshiruv resursi')
            ->assertDontSee('Eski hisobot resursi');

        $this->actingAs($viewer)
            ->get(route('upload.details', $acceptedDatum))
            ->assertOk()
            ->assertSee('Shaffof Reyting Ustozi')
            ->assertSee('Shaffof ilmiy maqola.pdf')
            ->assertSee('6.75')
            ->assertSee(route('upload.file.download', $acceptedDatum));

        $this->actingAs($viewer)
            ->get(route('upload.file.download', $acceptedDatum))
            ->assertOk()
            ->assertDownload('Shaffof ilmiy maqola.pdf');

        $this->actingAs($viewer)
            ->get(route('upload.details', $cancelledDatum))
            ->assertOk()
            ->assertSee('Qaytarilgan ilmiy maqola.pdf')
            ->assertSee('Qaytarilgan')
            ->assertSee('Talablarga mos kelmadi.')
            ->assertSee(route('upload.file.download', $cancelledDatum));

        $this->actingAs($viewer)
            ->get(route('upload.file.download', $cancelledDatum))
            ->assertOk()
            ->assertDownload('Qaytarilgan ilmiy maqola.pdf');

        $this->actingAs($viewer)
            ->get(route('upload.details', $pendingDatum))
            ->assertOk()
            ->assertSee('Yopiq tekshiruv resursi')
            ->assertDontSee('Resursni baholash')
            ->assertDontSee(route('upload.file.download', $pendingDatum));
        $this->actingAs($viewer)->get(route('upload.file.download', $pendingDatum))->assertForbidden();
        $this->actingAs($viewer)->get(route('upload.details', $oldDatum))->assertOk();

        $unknownRole = User::factory()->withRole('unknown')->create();
        $this->actingAs($unknownRole)->get(route('upload.details', $acceptedDatum))->assertForbidden();
        $this->actingAs($unknownRole)->get(route('upload.file.download', $acceptedDatum))->assertForbidden();
        $this->actingAs($unknownRole)->get(route('upload.details', $cancelledDatum))->assertForbidden();
        $this->actingAs($unknownRole)->get(route('upload.file.download', $cancelledDatum))->assertForbidden();
        $this->actingAs($unknownRole)->get(route('upload.details', $pendingDatum))->assertForbidden();
    }

    public function test_assigned_reviewer_can_start_assessment_from_pending_rating_resource_details(): void
    {
        $viewer = User::factory()->create();
        $reviewer = User::factory()->create();
        $owner = User::factory()->create();
        $report = $this->createReport('Faol hisobot', '1');
        $criterion = $this->createCriterion($report, 'Baholanadigan mezon', [
            'checking' => 'manual',
        ]);
        CriterionReviewerAssignment::query()->create([
            'criterion_id' => $criterion->getKey(),
            'hemis_id' => $reviewer->hemis_id,
            'criterion_code' => 'test/'.$criterion->getKey(),
        ]);
        $datum = $this->createPendingDatum($owner, $criterion, 'received');

        $this->actingAs($viewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertDontSee(route('reviews.show', $datum));

        $this->actingAs($reviewer)
            ->get(route('upload.details', $datum))
            ->assertOk()
            ->assertSee('Resursni baholash')
            ->assertSee(route('reviews.show', $datum));

        $this->actingAs($reviewer)
            ->get(route('reviews.show', $datum))
            ->assertOk()
            ->assertSee('Tasdiqlash')
            ->assertSee('Rad etish');
    }

    public function test_ratings_page_handles_the_absence_of_an_active_report(): void
    {
        $viewer = User::factory()->create(['degree' => 'hold_degrees']);

        $this->actingAs($viewer)
            ->get(route('ratings.index'))
            ->assertOk()
            ->assertSee('Faol hisobot topilmadi')
            ->assertViewHas('report', null);

        $this->actingAs($viewer)
            ->get(route('ratings.show', $viewer))
            ->assertOk()
            ->assertSee('Faol hisobot uchun kriteriyalar mavjud emas')
            ->assertSee('Jami: 0.00');
    }

    /** @return array<string, string> */
    private function userName(string $fullName): array
    {
        return [
            'full' => $fullName,
            'first' => $fullName,
            'last' => '',
            'third' => '',
            'short' => $fullName,
        ];
    }

    private function createDepartment(string $name, ?Department $parent = null): Department
    {
        return Department::query()->create([
            'id' => $this->referenceId++,
            'name' => ['uz' => $name, 'kaa' => $name, 'ru' => $name, 'en' => $name],
            'parent_id' => $parent?->getKey(),
        ]);
    }

    private function createWorkplace(User $user, Department $department, string $positionName): Workplace
    {
        $academicDegree = AcademicDegree::query()->create(['id' => $this->referenceId++, 'name' => 'PhD']);
        $academicRank = AcademicRank::query()->create(['id' => $this->referenceId++, 'name' => 'Dotsent']);
        $form = EmploymentForm::query()->firstOrCreate([
            'id' => EmploymentForm::PRIMARY_WORKPLACE_ID,
        ], [
            'name' => 'Asosiy ish joyi',
        ]);
        $staff = EmploymentStaff::query()->create(['id' => $this->referenceId++, 'name' => '1 stavka']);
        $position = StaffPosition::query()->create(['id' => $this->referenceId++, 'name' => $positionName]);
        $status = EmployeeStatus::query()->create(['id' => $this->referenceId++, 'name' => 'Ishlamoqda']);
        $type = EmployeeType::query()->create(['id' => $this->referenceId++, 'name' => 'Professor-o‘qituvchi']);

        return Workplace::query()->create([
            'user_id' => $user->getKey(),
            'department_id' => $department->getKey(),
            'academic_degree_id' => $academicDegree->getKey(),
            'academic_rank_id' => $academicRank->getKey(),
            'form_id' => $form->getKey(),
            'staff_id' => $staff->getKey(),
            'staff_position_id' => $position->getKey(),
            'status_id' => $status->getKey(),
            'type_id' => $type->getKey(),
        ]);
    }

    private function createReport(string $name, string $status): Report
    {
        return Report::query()->create([
            'name' => ['uz' => $name],
            'status' => $status,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function createCriterion(Report $report, string $name, array $attributes = []): Criterion
    {
        return Criterion::query()->create(array_merge([
            'name' => ['uz' => $name],
            'report_id' => $report->getKey(),
            'status' => '1',
        ], $attributes));
    }

    private function createAcceptedDatum(
        User $user,
        Criterion $criterion,
        float $point,
        string $name = 'Tasdiqlangan resurs',
    ): Datum {
        return Datum::query()->create([
            'name' => $name,
            'material' => [],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => 'accepted',
            'point' => $point,
        ]);
    }

    private function createPendingDatum(User $user, Criterion $criterion, string $status): Datum
    {
        return Datum::query()->create([
            'name' => 'Baholanmagan resurs',
            'material' => [],
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'status' => $status,
            'point' => 0,
        ]);
    }

    private function createPoint(User $user, Criterion $criterion, Report $report, float $point): Point
    {
        if ($criterion->parent_id === null) {
            $parent = $this->createCriterion($report, 'Reyting bo‘limi '.$criterion->getKey());
            $criterion->update(['parent_id' => $parent->getKey()]);
        }

        return Point::query()->create([
            'user_id' => $user->getKey(),
            'criterion_id' => $criterion->getKey(),
            'report_id' => $report->getKey(),
            'point' => $point,
        ]);
    }
}
