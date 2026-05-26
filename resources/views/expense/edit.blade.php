<form action="{{ route('expense.update', $expense->id) }}" method="POST" enctype="multipart/form-data">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            <label for="title" class="form-label">{{ __('Title') }}</label>
            <input type="text" name="title" id="title" class="form-control" placeholder="{{ __('Enter Expense title') }}" value="{{ old('title', $expense->title) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="type" class="form-label">{{ __('Type') }}</label>
            <select name="type" id="type" class="form-control hidesearch ">
                @foreach($types as $val => $label)
                    <option value="{{ $val }}" {{ old('type', $expense->type) == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="vehicle" class="form-label">{{ __('Vehicle') }}</label>
            <select name="vehicle" id="vehicle" class="form-control hidesearch ">
                @foreach($vehicles as $val => $label)
                    <option value="{{ $val }}" {{ old('vehicle', $expense->vehicle) == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group col-md-6">
            <label for="date" class="form-label">{{ __('Date') }}</label>
            <input type="date" name="date" id="date" class="form-control" value="{{ old('date', $expense->date) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="amount" class="form-label">{{ __('Total Amount') }}</label>
            <input type="number" name="amount" id="amount" class="form-control" placeholder="{{ __('Enter expense amount') }}" value="{{ old('amount', $expense->amount) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="receipt" class="form-label">{{ __('Receipt') }}</label>
            <input type="file" name="receipt" id="receipt" class="form-control">
        </div>
        <div class="form-group col-md-12">
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea name="notes" id="notes" class="form-control" placeholder="{{ __('Enter notes') }}" rows="2">{{ old('notes', $expense->notes) }}</textarea>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>


