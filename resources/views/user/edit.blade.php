<form action="{{ route('users.update', $user->id) }}" method="POST">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        @if (\Auth::user()->type != 'super admin')
            <div class="form-group col-md-6">
                <label for="role" class="form-label">{{ __('Assign Role') }}</label>
                <select name="role" id="role" class="form-control hidesearch " required>
                    @foreach($userRoles as $val => $label)
                        <option value="{{ $val }}" {{ old('role', !empty($user->roles) ? $user->roles[0]->id : null) == $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="form-group col-md-6">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter Name') }}" value="{{ old('name', $user->name) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="email" class="form-label">{{ __('User Email') }}</label>
            <input type="text" name="email" id="email" class="form-control" placeholder="{{ __('Enter User Email') }}" value="{{ old('email', $user->email) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="phone_number" class="form-label">{{ __('User Phone Number') }}</label>
            <input type="text" name="phone_number" id="phone_number" class="form-control" placeholder="{{ __('Enter Phone Number') }}" value="{{ old('phone_number', $user->phone_number) }}">
        </div>


    </div>
</div>
<div class="modal-footer">
    <button type="submit" class="btn btn-primary btn-rounded">{{__('Update')}}</button>
</div>
</form>
