<form action="{{ route('setting.smtp.testing') }}" method="POST">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="email" class="form-label">{{ __('Test Email') }}</label>
            <input type="text" name="email" id="email" class="form-control" placeholder="{{ __('Enter User Email') }}" value="{{ old('email', Auth::user()->email) }}" required>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="submit" class="btn btn-primary btn-rounded">{{__('Send Mail')}}</button>
</div>
</form>
