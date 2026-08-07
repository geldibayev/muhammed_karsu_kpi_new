@props(['deadline', 'deadlineLabel', 'isOpen'])

<div {{ $attributes->class([
        'alert rounded-0 border-left-0 border-right-0 mb-0 text-center shadow-sm',
        'alert-warning' => $isOpen,
        'alert-danger' => ! $isOpen,
    ]) }}
     data-testid="resource-upload-deadline"
     role="alert">
    <i @class([
            'fas mr-1',
            'fa-clock' => $isOpen,
            'fa-lock' => ! $isOpen,
        ]) aria-hidden="true"></i>
    <strong>Resurs yuklashning oxirgi muddati:</strong>
    <time datetime="{{ $deadline->toIso8601String() }}">
        {{ $deadlineLabel }} gacha
    </time>
    (Toshkent vaqti).
    @if($isOpen)
        Belgilangan vaqtdan keyin yangi resurslar qabul qilinmaydi.
    @else
        Muddat yakunlangan, yangi resurs yuklash yopilgan.
    @endif
</div>
