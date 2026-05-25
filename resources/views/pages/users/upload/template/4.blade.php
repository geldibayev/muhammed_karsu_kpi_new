<div class="row">
    <div class="col-md-9">
        <b>

        </b>
        <div class="form-group">
            <label for="material_name" class="mb-0 small">{{ __('checking.publish_name') }}</label>
            <input type="text" name="data[material_name]" class="form-control" id="material_name" required>
        </div>
    </div>
    <div class="col-md-3">
        <div class="form-group">
            <label for="material_form" class="mb-0 small">{{ __('checking.publish_form') }}</label>
            <select class="form-control" name="data[material_form]" id="material_form" required>
                <option value="10">{{ __('checking.int_name_10') }}</option>
                <option value="11">{{ __('checking.int_name_11') }}</option>
                <option value="12">{{ __('checking.int_name_12') }}l</option>
                <option value="13">{{ __('checking.int_name_13') }}</option>
                <option value="14">{{ __('checking.int_name_14') }}</option>
                <option value="15">{{ __('checking.int_name_15') }}</option>
                <option value="16">{{ __('checking.int_name_16') }}</option>
                <option value="17">{{ __('checking.int_name_17') }}</option>
            </select>
        </div>
    </div>
    <div class="col-md-8">
        <div class="form-floating">
            <div class="form-group">
                <label for="material_reg_no" class="mb-0 small">{{ __('checking.material_reg_no') }}</label>
                <input type="text" name="data[material_reg_no]" class="form-control" id="material_reg_no" required>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="form-floating">
            <div class="form-group">
                <label for="material_date" class="mb-0 small">{{ __('checking.material_date') }}</label>
                <input type="date" name="data[material_date]" class="form-control" id="material_date">
            </div>
        </div>
    </div>
    <div class="col-md-2">
        <div class="form-group">
            <label for="authors_count" class="mb-0 small">{{ __('checking.authors_count') }}</label>
            <input type="number" name="authors_count" class="form-control" id="authors_count" value="1"
                   required>
        </div>
    </div>
    <div class="col-md-10">
        <div class="form-floating">
            <div class="form-group">
                <label for="authors_list" class="mb-0 small">{{ __('checking.authors_list') }}</label>
                <input type="text" name="data[authors_list]" class="form-control" required>
            </div>
        </div>
    </div>
    <div class="col-md-12">
        <div class="form-floating">
            <div class="form-group">
                <label for="publish_params" class="mb-0 small">{{ __('checking.publish_params') }}</label>
                <input type="text" name="data[publish_params]" class="form-control" id="publish_params" required>
            </div>
        </div>
    </div>
</div>
