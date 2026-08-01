<form action="{{ route('criteria.update', $criterion->id) }}" method="POST">
    @method('PUT')
    @csrf
    <div><b>{{ $criterion->name['uz'] }}</b></div>
    @php($description = strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", data_get($criterion->desc, 'uz', ''))))
    <div style="white-space: pre-line">{{ $description }}</div>
    <textarea name="ai_prompt" rows="50" style="width: 100%">{{ $criterion->ai_prompt }}</textarea>
    <button type="submit">OK</button>
</form>
