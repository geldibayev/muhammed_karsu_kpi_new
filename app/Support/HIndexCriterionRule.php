<?php

namespace App\Support;

class HIndexCriterionRule
{
    public const CODE = '3.1.4';

    public const DESCRIPTION_UZ = 'Toifa ulushi ilmiy darajali professor-o‘qituvchilar uchun 3 ball, boshqa toifalar uchun 2 ball. Har bir bazadagi H-index balli shu ulushdan aniqlanadi: h≤2 — 25%, h=3 — 50%, h=4 — 75%, h≥5 — 100%; h=5 dan yuqori har bir birlik uchun yana 1 ball qo‘shiladi. Web of Science balli alohida olinadi. Scopus va ResearchGate ballari solishtirilib, faqat kattasi hisobga olinadi. Yakuniy ball: Web of Science balli + max(Scopus balli, ResearchGate balli).';
}
