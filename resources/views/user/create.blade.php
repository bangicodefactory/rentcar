<form action="{{ url('users') }}" method="POST">
@csrf
<div class="modal-body">
    <div class="row">
        @if(\Auth::user()->type != 'super admin')
            <div class="form-group col-md-6">
                <label for="role" class="form-label">{{ __('Assign Role') }}</label>
                <select name="role" id="role" class="form-control hidesearch" required>
                    @foreach($userRoles as $val => $label)
                        <option value="{{ $val }}" {{ old('role') == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="form-group col-md-6">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter Name') }}" value="{{ old('name') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="email" class="form-label">{{ __('User Email') }}</label>
            <input type="text" name="email" id="email" class="form-control" placeholder="{{ __('Enter user email') }}" value="{{ old('email') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="password" class="form-label">{{ __('User Password') }}</label>
            <input type="password" name="password" id="password" class="form-control" placeholder="{{ __('Enter user password') }}" required minlength="6">
        </div>
        <div class="form-group col-md-6">
            <label for="phone_number" class="form-label">{{ __('User Phone Number') }}</label>
            <input type="text" name="phone_number" id="phone_number" class="form-control" placeholder="{{ __('Enter user phone number') }}" value="{{ old('phone_number') }}">
        </div>

    </div>
</div>
<div class="modal-footer">
    <button type="submit" class="btn btn-primary ml-10">{{__('Create')}}</button>
</div>
</form>
