<form action="{{ url('permission') }}" method="POST">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group ">
            <label for="title" class="form-label ">{{ __('Permission Title') }}</label>
            <input type="text" name="title" id="title" class="form-control" value="{{ old('title') }}">
        </div>
        <div class="form-group">
            <label for="user_roles" class="form-label">{{ __('User Roles') }}</label>
            <select name="user_roles[]" id="user_roles" class="form-control hidesearch" multiple required>
                @foreach($userRoles as $val => $label)
                    <option value="{{ $val }}" {{ in_array($val, old('user_roles', [])) ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-12">
            <button type="submit" class="btn btn-primary btn-rounded">{{__('Create')}}</button>
        </div>
    </div>
</div>
</form>
