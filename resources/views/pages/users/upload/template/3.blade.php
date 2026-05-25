<div class="col-md-12">
    <div class="form-group">
        <label for="material_name" class="mb-0 small">Nashr nomi</label>
        <input type="text" name="article[name]" class="form-control" id="material_name" required>
    </div>
</div>
<div class="col-md-9">
    <div class="form-group">
        <label for="material_keywords" class="mb-0 small">Kalit sozlar</label>
        <input type="text" name="article[keywords]" class="form-control" id="material_keywords"
               required>
    </div>
</div>
<div class="col-md-3">
    <div class="form-group">
        <label for="material_lang" class="mb-0 small">Nashr tili</label>
        <select class="form-control" name="article[lang]" id="material_lang" required>
            @foreach($languages as $lang)
                <option value="{{$lang->id}}">{{ $lang->name['uz'] }}</option>
            @endforeach
        </select>
    </div>
</div>
<div class="col-md-2">
    <div class="form-group">
        <label for="authors_count" class="mb-0 small">Mualliflar soni</label>
        <input type="number" name="article[authors_num]" class="form-control"
               id="authors_count" value="1" required>
    </div>
</div>
<div class="col-md-10">
    <div class="form-floating">
        <div class="form-group">
            <label for="authors_list" class="mb-0 small">Mualliflar ro‘yxati</label>
            <input type="text" name="article[authors]" class="form-control"
                   id="authors_list" required>
        </div>
    </div>
</div>
<div class="col-md-12">
    <div class="form-floating">
        <div class="form-group">
            <label for="doi" class="mb-0 small">DOI</label>
            <input type="text" name="article[doi]" class="form-control" id="doi">
        </div>
    </div>
</div>
<div class="col-md-7">
    <div class="form-group">
        <label for="journal" class="mb-0 small">Jurnal nomi</label>
        <input type="text" name="article[journal]" class="form-control" id="journal" required>
    </div>
</div>
<div class="col-md-5">
    <div class="form-floating">
        <div class="form-group">
            <label for="publish_params" class="mb-0 small">Nashr parametrlari</label>
            <input type="text" name="article[params]" class="form-control" id="publish_params"
                   required>
        </div>
    </div>
</div>
