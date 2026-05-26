<form action="{{ url('expense-type') }}" method="POST">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="title" class="form-label">{{ __('Title') }}</label>
            <input type="text" name="title" id="title" class="form-control" placeholder="{{ __('Enter title') }}" value="{{ old('title') }}" required>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Create')}}</button>
</div>
</form>

