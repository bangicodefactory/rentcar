<form action="{{ route('vehicle-type.update', $vehicleType->id) }}" method="POST">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="type" class="form-label">{{ __('Type') }}</label>
            <input type="text" name="type" id="type" class="form-control" placeholder="{{ __('Enter type') }}" value="{{ old('type', $vehicleType->type) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea name="notes" id="notes" class="form-control" placeholder="{{ __('Enter notes') }}" rows="2">{{ old('notes', $vehicleType->notes) }}</textarea>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>
