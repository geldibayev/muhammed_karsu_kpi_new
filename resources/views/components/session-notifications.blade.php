<script>
    $(function () {
        if (typeof toastr === 'undefined') {
            return;
        }

        toastr.options = {
            closeButton: true,
            progressBar: true,
            positionClass: 'toast-bottom-right',
            timeOut: 7000,
            extendedTimeOut: 1500,
        };

        @foreach(['success', 'error', 'warning', 'info'] as $notificationType)
            @if(session()->has($notificationType))
                toastr.{{ $notificationType }}({{ Illuminate\Support\Js::from(session($notificationType)) }});
            @endif
        @endforeach
    });
</script>
