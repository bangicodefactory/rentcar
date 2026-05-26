<form action="{{ route('credit.store') }}" method="POST" id="credit-create-form">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="driver_id" class="form-label">{{ __('credit.driver') }}</label>
            <select name="driver_id" id="credit_driver_id" class="form-control basic-select" required>
                <option value="">{{ __('credit.search_and_select_driver') }}</option>
                @foreach ($drivers as $d)
                    <option value="{{ $d->id }}">{{ $d->name }} @if($d->phone_number) ({{ $d->phone_number }}) @endif</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="amount" class="form-label">{{ __('credit.amount') }}</label>
            <input type="number" name="amount" id="amount" class="form-control" placeholder="{{ __('credit.amount') }}" step="0.01" min="0" value="{{ old('amount') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="status" class="form-label">{{ __('credit.status') }}</label>
            <select name="status" id="status" class="form-control basic-select">
                @foreach($statuses as $val => $label)
                    <option value="{{ $val }}" {{ old('status', 'non payé') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-12">
            <label for="credit_date" class="form-label">{{ __('credit.date_credit') }}</label>
            <input type="date" name="credit_date" id="credit_date" class="form-control" value="{{ old('credit_date', date('Y-m-d')) }}">
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{ __('credit.close') }}</button>
    <button type="submit" class="btn btn-primary">{{ __('credit.add_credit') }}</button>
</div>
</form>
