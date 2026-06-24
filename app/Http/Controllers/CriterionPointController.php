<?php

namespace App\Http\Controllers;

use App\Models\Criterion;
use App\Models\CriterionEvaluation;
use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Point;
use Illuminate\Http\Request;

class CriterionPointController extends Controller
{
    public function reload()
    {
        CriterionPoint::where('report_id', 1)->delete();
        $data = Datum::with('criterion')->where('status', 'accepted')->get();
        foreach ($data as $datum) {
            $pts = CriterionPoint::firstOrCreate(
                [
                    'user_id' => $datum->user_id,
                    'criterion_id' => $datum->criterion_id,
                    'report_id' => 1,
                ],
                [
                    'point' => 0,
                    'files' => 0,
                ]
            );
            $pts->point += $datum->point;
            $pts->files += 1;
            $pts->save();
        }
    }

    public function pointing()
    {
        Point::where('report_id', 1)->delete();
        $criteria = Criterion::whereNotNull('parent_id')->get();
        foreach ($criteria as $criterion) {
            $gets = CriterionPoint::where('criterion_id', $criterion->id)->get();
            $maxPoint = 0;
            if ($criterion->formula_id == 1) {
                $maxPoint = CriterionPoint::where('criterion_id', $criterion->id)->max('point') ?? 0;
            }
            $critPoint = $criterion->point ?? 0;
            foreach ($gets as $get) {
                $calculatedPoint = 0;
                $userPoint = $get->point ?? 0;
                if ($criterion->formula_id == 1) {
                    if ($maxPoint > 0) {
                        $calculatedPoint = $critPoint * ($userPoint / $maxPoint);
                    }
                }
                if ($criterion->formula_id == 2) {
                    $critPoint = CriterionEvaluation::where('criterion_id', $criterion->id)->where('evaluation', $get->user->degree)->first()->score ?? 0;
                    $calculatedPoint = min($userPoint, $critPoint);
                }
                if ($criterion->formula_id == 3) {
                    $calculatedPoint = $userPoint;
                }

                Point::updateOrCreate(
                    [
                        'user_id' => $get->user_id,
                        'criterion_id' => $get->criterion_id,
                        'report_id' => 1,
                    ],
                    [
                        'point' => is_numeric($calculatedPoint) ? $calculatedPoint : 0,
                    ]
                );
            }
        }
    }

    /*public function pointing()
    {
        $criteria = Criterion::whereNotNull('parent_id')->get();
        foreach ($criteria as $criterion) {
            if ($criterion->formula_id == 1) {
                // Eng yuqori ballni oling va proportsional solishtirish orqali ball bering
                $maxPoint = CriterionPoint::where('criterion_id', $criterion->id)->max('point');
                $gets = CriterionPoint::where('criterion_id', $criterion->id)->get();
                foreach ($gets as $get) {
                    Point::firstOrCreate([
                        'user_id' => $get->user_id,
                        'criterion_id' => $get->criterion_id,
                        'report_id' => 1,
                    ], [
                        'point' => $criterion->point * ($get->point / $maxPoint),
                    ]);
                }
            } elseif ($criterion->formula_id == 2) {
                // $criterion->point dan oshib ketsa boshqa qo'shilmasligi kerak, ya'ni maksimal balldan oshmang
                $gets = CriterionPoint::where('criterion_id', $criterion->id)->get();
                foreach ($gets as $get) {
                    Point::firstOrCreate([
                        'user_id' => $get->user_id,
                        'criterion_id' => $get->criterion_id,
                        'report_id' => 1,
                    ], [
                        'point' => $get->point / $maxPoint,
                    ]);
                }
            } elseif ($criterion->formula_id == 3) {
                // Cheklanmagan tarzda ball berilishi mumkin
                $gets = CriterionPoint::where('criterion_id', $criterion->id)->get();
                foreach ($gets as $get) {
                    Point::firstOrCreate([
                        'user_id' => $get->user_id,
                        'criterion_id' => $get->criterion_id,
                        'report_id' => 1,
                    ], [
                        'point' => $get->point / $maxPoint,
                    ]);
                }
            }
        }
    }*/
}
