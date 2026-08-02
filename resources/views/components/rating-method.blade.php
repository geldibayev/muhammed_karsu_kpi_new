@props(['method', 'criterion'])

@php($modalId = 'rating-method-'.$criterion->getKey())

<button type="button"
        class="btn btn-outline-info btn-sm text-left"
        data-toggle="modal"
        data-target="#{{ $modalId }}"
        data-testid="rating-method-button"
        aria-controls="{{ $modalId }}">
    <i class="fas fa-calculator mr-1" aria-hidden="true"></i>
    {{ $method['label'] }}
</button>

<div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog"
     aria-labelledby="{{ $modalId }}-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content text-left">
            <div class="modal-header bg-light">
                <div>
                    <div class="small text-muted mb-1">{{ $criterion->code }}</div>
                    <h4 class="modal-title h5 font-weight-bold" id="{{ $modalId }}-title">
                        {{ $method['label'] }}
                    </h4>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h5 class="h6 font-weight-bold mb-2">Qanday hisoblanadi?</h5>
                <p class="mb-3">{{ $method['explanation'] }}</p>

                @if($method['maximum'] !== null && $method['key'] !== 'unlimited')
                    <div class="d-flex align-items-center justify-content-between border rounded px-3 py-2 mb-3">
                        <span class="text-muted">Sizning toifangiz uchun maksimal ball</span>
                        <span class="badge badge-success px-3 py-2">
                            {{ number_format($method['maximum'], 2) }} ball
                        </span>
                    </div>
                @endif

                <div class="alert alert-warning mb-3">
                    <div class="font-weight-bold mb-1">
                        <i class="fas fa-info-circle mr-1" aria-hidden="true"></i> Izoh
                    </div>
                    <div class="small">{{ $method['note'] }}</div>
                </div>

                <div class="alert alert-info mb-0">
                    <div class="font-weight-bold mb-1">
                        <i class="fas fa-lightbulb mr-1" aria-hidden="true"></i> Oddiy misol
                    </div>
                    <div class="small">{{ $method['example'] }}</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Yopish</button>
            </div>
        </div>
    </div>
</div>
