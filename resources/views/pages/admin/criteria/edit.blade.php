<form action="{{ route('criteria.update', $criterion->id) }}" method="POST">
    @method('PUT')
    @csrf
    <div><b>{{ $criterion->name['uz'] }}</b></div>
    <div>{!! $criterion->desc['uz'] !!}</div>
    <textarea name="ai_prompt" rows="50" style="width: 100%">{{ $criterion->ai_prompt }}</textarea>
    <button type="submit">OK</button>
</form>
