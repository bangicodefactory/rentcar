<form action="{{ url('driver') }}" method="POST" enctype="multipart/form-data">
@csrf
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            <label for="first_name" class="form-label">{{ __('First Name') }}</label>
            <input type="text" name="first_name" id="first_name" class="form-control" placeholder="{{ __('Enter First Name') }}" value="{{ old('first_name') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="last_name" class="form-label">{{ __('Last Name') }}</label>
            <input type="text" name="last_name" id="last_name" class="form-control" placeholder="{{ __('Enter First Name') }}" value="{{ old('last_name') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="email" class="form-label">{{ __('Email') }}</label>
            <input type="text" name="email" id="email" class="form-control" placeholder="{{ __('Enter Email') }}" value="{{ old('email') }}">
        </div>
        <div class="form-group col-md-6">
            <label for="phone_number" class="form-label">{{ __('Phone Number') }}</label>
            <input type="text" name="phone_number" id="phone_number" class="form-control" placeholder="{{ __('Enter Phone Number') }}" value="{{ old('phone_number') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="gender" class="form-label">{{ __('Gender') }}</label>
            <select name="gender" id="gender" class="form-control hidesearch " required>
                @foreach($gender as $val => $label)
                    <option value="{{ $val }}" {{ old('gender') == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="age" class="form-label">{{ __('age') }}</label>
            <input type="number" name="age" id="age" class="form-control" placeholder="{{ __('Enter age') }}" value="{{ old('age') }}">
        </div>
        <div class="form-group col-md-6">
            <label for="birth_date" class="form-label">{{ __('Birth date') }}</label>
            <input type="date" name="birth_date" id="birth_date" class="form-control" value="{{ old('birth_date') }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="address" class="form-label">{{ __('Address') }}</label>
            <textarea name="address" id="address" class="form-control" placeholder="{{ __('Enter address') }}" rows="1" required>{{ old('address') }}</textarea>
        </div>

        <div class="form-group col-md-12">
            <label for="license_number" class="form-label">{{ __('License Number') }}</label>
            <input type="text" name="license_number" id="license_number" class="form-control" placeholder="{{ __('Enter license number') }}" value="{{ old('license_number') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="issue_date" class="form-label">{{ __('Issue Date') }}</label>
            <input type="date" name="issue_date" id="issue_date" class="form-control" value="{{ old('issue_date') }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="expiration_date" class="form-label">{{ __('Expiration Date') }}</label>
            <input type="date" name="expiration_date" id="expiration_date" class="form-control" value="{{ old('expiration_date') }}" required>
        </div>

        <div class="form-group col-md-6">
            <label for="license" class="form-label">{{ __('License 1:') }}</label>
            <input type="file" name="license" id="license" class="form-control">
        </div>
        <div class="form-group col-md-6">
            <label for="license1" class="form-label">{{ __('License 2:') }}</label>
            <input type="file" name="license1" id="license1" class="form-control">
        </div>
        <div class="form-group col-md-12">
            <label for="reference" class="form-label">{{ __('Reference') }}</label>
            <input type="text" name="reference" id="reference" class="form-control" placeholder="{{ __('Enter reference') }}" value="{{ old('reference') }}">
        </div>
        <div class="form-group col-md-6">
            <label for="document" class="form-label">{{ __('ID file 1:') }}</label>
            <input type="file" name="document" id="document" class="form-control">
        </div>
        <div class="form-group col-md-6">
            <label for="document1" class="form-label">{{ __('ID file 2:') }}</label>
            <input type="file" name="document1" id="document1" class="form-control">
        </div>
        <div class="form-group col-md-12">
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea name="notes" id="notes" class="form-control" placeholder="{{ __('Enter notes') }}" rows="1">{{ old('notes') }}</textarea>
        </div>
        <div class="form-group col-md-12">
            <label for="ICE_company" class="form-label">{{ __('ICE_company') }}</label>
            <input type="text" name="ICE_company" id="ICE_company" class="form-control" placeholder="{{ __('Enter ICE if client company') }}" value="{{ old('ICE_company') }}">
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Create')}}</button>
</div>
</form>
