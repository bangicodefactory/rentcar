<form action="{{ url('coupons') }}" method="POST">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group  col-md-6">
            <label for="name" class="form-label">{{ __('Coupon Name') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter coupon name') }}" value="{{ old('name') }}">
        </div>
        <div class="form-group col-md-6">
            <label for="type" class="form-label">{{ __('Coupon Type') }}</label>
            <select name="type" id="type" class="form-control hidesearch">
                @foreach($type as $val => $label)
                    <option value="{{ $val }}" {{ old('type') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group  col-md-6">
            <label for="code" class="form-label">{{ __('Coupon Code') }}</label>
            <input type="text" name="code" id="code" class="form-control" placeholder="{{ __('Enter coupon code') }}" value="{{ old('code') }}">
        </div>
        <div class="form-group  col-md-6">
            <label for="rate" class="form-label">{{ __('Discount Rate') }}</label>
            <input type="number" name="rate" id="rate" class="form-control" placeholder="{{ __('Enter coupon discount rate') }}" value="{{ old('rate') }}">
        </div>
        <div class="form-group  col-md-6">
            <label for="valid_for" class="form-label">{{ __('Valid For') }}</label>
            <input type="date" name="valid_for" id="valid_for" class="form-control" value="{{ old('valid_for') }}">
        </div>
        <div class="form-group  col-md-6">
            <label for="use_limit" class="form-label">{{ __('Number Of Times This Coupon Can Be Used') }}</label>
            <input type="number" name="use_limit" id="use_limit" class="form-control" placeholder="{{ __('Enter coupon use limit') }}" value="{{ old('use_limit') }}">
        </div>
        <div class="form-group col-md-6">
            <label for="applicable_packages" class="form-label">{{ __('Applicable Packages') }}</label>
            <select name="applicable_packages[]" id="applicable_packages" class="form-control hidesearch basic-select" multiple>
                @foreach($packages as $val => $label)
                    <option value="{{ $val }}" {{ in_array($val, old('applicable_packages', [])) ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="status" class="form-label">{{ __('Status') }}</label>
            <select name="status" id="status" class="form-control hidesearch">
                @foreach($status as $val => $label)
                    <option value="{{ $val }}" {{ old('status') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary btn-rounded">{{__('Create')}}</button>
</div>
</form>


