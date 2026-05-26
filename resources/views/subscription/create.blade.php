<form action="{{ url('subscriptions') }}" method="POST">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group">
            <label for="title" class="form-label">{{ __('Title') }}</label>
            <input type="text" name="title" id="title" class="form-control" placeholder="{{ __('Enter subscription title') }}" value="{{ old('title') }}" required>
        </div>
        <div class="form-group">
            <label for="interval" class="form-label">{{ __('Interval') }}</label>
            <select name="interval" id="interval" class="form-control hidesearch" required>
                @foreach($intervals as $val => $label)
                    <option value="{{ $val }}" {{ old('interval') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="package_amount" class="form-label">{{ __('Package Amount') }}</label>
            <input type="number" name="package_amount" id="package_amount" class="form-control" placeholder="{{ __('Enter package amount') }}" value="{{ old('package_amount') }}" step="0.01">
        </div>
        <div class="form-group">
            <label for="user_limit" class="form-label">{{ __('User Limit') }}</label>
            <input type="number" name="user_limit" id="user_limit" class="form-control" placeholder="{{ __('Enter user limit') }}" value="{{ old('user_limit') }}" required>
        </div>
        <div class="form-group">
            <label for="driver_limit" class="form-label">{{ __('Driver Limit') }}</label>
            <input type="number" name="driver_limit" id="driver_limit" class="form-control" placeholder="{{ __('Enter driver limit') }}" value="{{ old('driver_limit') }}" required>
        </div>
        <div class="form-group">
            <label for="enabled_logged_history" class="form-label">{{ __('Show User Logged History') }}</label>
            <div>
                <label class="switch with-icon switch-primary">
                    <input type="checkbox" name="enabled_logged_history" id="enabled_logged_history"><span class="switch-btn"></span>
                </label>
            </div>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button type="submit" class="btn btn-primary btn-rounded">{{__('Create')}}</button>
</div>
</form>
