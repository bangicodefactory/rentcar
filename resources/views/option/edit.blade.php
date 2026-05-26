<form action="{{ route('option.update', $option->id) }}" method="POST">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="name" class="form-label">{{ __('Option') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter option') }}" value="{{ old('name', $option->name) }}" required>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>
