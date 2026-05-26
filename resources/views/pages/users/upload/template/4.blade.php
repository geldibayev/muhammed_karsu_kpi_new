<div class="col-md-9">
    <div class="form-group">
        <label for="name" class="mb-0 small">Guvohnoma nomi</label>
        <input type="text" name="data[name]" class="form-control" id="name" required>
    </div>
</div>
<div class="col-md-3">
    <div class="form-group">
        <label for="form" class="mb-0 small">Mulk turi</label>
        <select class="form-control" name="data[form]" id="form" required>
            <option value="10">Boshqa</option>
            <option value="11">Ixtiro</option>
            <option value="12">Foydali model</option>
            <option value="13">Sanoat namunasi</option>
            <option value="14">Seleksiya yutuqlari</option>
            <option value="15">Tovar belgisi</option>
            <option value="16">Firma nomlari</option>
            <option value="17">EHM dasturi va ma'lumotlar bazasi</option>
        </select>
    </div>
</div>
<div class="col-md-8">
    <div class="form-floating">
        <div class="form-group">
            <label for="certificate_no" class="mb-0 small">Guvohnoma raqami</label>
            <input type="text" name="data[certificate_no]" class="form-control" id="certificate_no" required>
        </div>
    </div>
</div>
<div class="col-md-4">
    <div class="form-floating">
        <div class="form-group">
            <label for="certificate_date" class="mb-0 small">Guvohnoma sanasi</label>
            <input type="date" name="data[certificate_date]" class="form-control" id="certificate_date">
        </div>
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
            <input type="text" name="data[authors]" id="authors" class="form-control" required>
        </div>
    </div>
</div>
<div class="col-md-12">
    <div class="form-floating">
        <div class="form-group">
            <label for="publish_params" class="mb-0 small">Nashr parametrlari</label>
            <input type="text" name="data[publish_params]" class="form-control" id="publish_params" required>
        </div>
    </div>
</div>
