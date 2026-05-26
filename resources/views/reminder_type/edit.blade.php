<form action="{{ route('reminder-type.update', $reminderType->id) }}" method="POST">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="type" class="form-label">{{ __('Title') }}</label>
            <input type="text" name="type" id="type" class="form-control" placeholder="{{ __('Enter title') }}" value="{{ old('type', $reminderType->type) }}" required>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>
