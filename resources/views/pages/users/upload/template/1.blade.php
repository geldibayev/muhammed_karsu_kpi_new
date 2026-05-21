<div class="col-md-9">
    <div class="form-group">
        <label for="name" class="mb-0 small">Nashr nomi</label>
        <input type="text" name="data[name]"
               class="form-control @error('data.name') is-invalid @enderror" id="name"
               required>
    </div>
</div>
<div class="col-md-3">
    <div class="form-group">
        <label for="language_id" class="mb-0 small">Nashr tili</label>
        <select class="form-control" name="language_id" id="language_id" required>
            @foreach($languages as $lang)
                <option value="{{$lang->id}}">{{ $lang->name['uz'] }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="col-md-2">
    <div class="form-group">
        <label for="division" class="mb-0 small">Mualliflar soni</label>
        <input type="number" name="data[division]" class="form-control" id="division" value="1"
               required>
    </div>
</div>
<div class="col-md-10">
    <div class="form-floating">
        <div class="form-group">
            <label for="authors" class="mb-0 small">Mualliflar</label>
            <input type="text" name="data[authors]"
                   class="form-control @error('data.authors') is-invalid @enderror" id="authors"
                   required>
        </div>
    </div>
</div>
<div class="col-md-7">
    <div class="form-floating">
        <div class="form-group">
            <label for="publisher" class="mb-0 small">Nashriyot</label>
            <input type="text" name="data[publisher]"
                   class="form-control @error('data.publisher') is-invalid @enderror" id="publisher"
                   required>
        </div>
    </div>
</div>
<div class="col-md-5">
    <div class="form-floating">
        <div class="form-group">
            <label for="publish_params" class="mb-0 small">Nashr parametrlari</label>
            <input type="text" name="data[publish_params]"
                   class="form-control @error('data.publish_params') is-invalid @enderror" id="publish_params"
                   required>
        </div>
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        <label for="certificate_no" class="mb-0 small">Guvohnoma raqami</label>
        <input type="text" name="data[certificate_no]" class="form-control" id="certificate_no"
               aria-describedby="certificate_noHelp">
    </div>
</div>
<div class="col-md-6">
    <div class="form-floating">
        <div class="form-group">
            <label for="certificate_date" class="mb-0 small">Guvohnoma sanasi</label>
            <input type="date" name="data[certificate_date]" class="form-control" id="certificate_date">
        </div>
    </div>
</div>
