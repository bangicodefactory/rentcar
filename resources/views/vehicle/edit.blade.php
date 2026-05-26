<form action="{{ route('vehicle.update', $vehicle->id) }}" method="POST" enctype="multipart/form-data">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6">
            <label for="name" class="form-label">{{ __('Vehicle Name') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter vehicle name') }}" value="{{ old('name', $vehicle->name) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="type" class="form-label">{{ __('Type') }}</label>
            <select name="type" id="type" class="form-control hidesearch " required>
                @foreach($types as $val => $label)
                    <option value="{{ $val }}" {{ old('type', $vehicle->type) == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="model" class="form-label">{{ __('Model') }}</label>
            <input type="text" name="model" id="model" class="form-control" placeholder="{{ __('Enter model') }}" value="{{ old('model', $vehicle->model) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="engine_type" class="form-label">{{ __('Engine Type') }}</label>
            <input type="text" name="engine_type" id="engine_type" class="form-control" placeholder="{{ __('Enter engine type') }}" value="{{ old('engine_type', $vehicle->engine_type) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="engine_no" class="form-label">{{ __('Engine Number') }}</label>
            <input type="text" name="engine_no" id="engine_no" class="form-control" placeholder="{{ __('Enter engine number') }}" value="{{ old('engine_no', $vehicle->engine_no) }}">
        </div>
        <div class="form-group col-md-6">
            <label for="license_plate" class="form-label">{{ __('License Plate') }}</label>
            <input type="text" name="license_plate" id="license_plate" class="form-control" placeholder="{{ __('Enter license plate') }}" value="{{ old('license_plate', $vehicle->license_plate) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="registration_expiry_date" class="form-label">{{ __('Registration Expiry Date') }}</label>
            <input type="date" name="registration_expiry_date" id="registration_expiry_date" class="form-control" value="{{ old('registration_expiry_date', $vehicle->registration_expiry_date) }}">
        </div>
        <div class="form-group col-md-6">
            <label for="daily_rate" class="form-label">{{ __('Daily Rate') }}</label>
            <input type="number" name="daily_rate" id="daily_rate" class="form-control" placeholder="{{ __('Enter daily rate') }}" value="{{ old('daily_rate', $vehicle->daily_rate) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="year_of_ﬁrst_immatriculation" class="form-label">{{ __('Year of First Immatriculation') }}</label>
            <input type="number" name="year_of_ﬁrst_immatriculation" id="year_of_ﬁrst_immatriculation" class="form-control" placeholder="{{ __('Enter Year of First Immatriculation') }}" value="{{ old('year_of_ﬁrst_immatriculation', $vehicle->year_of_ﬁrst_immatriculation) }}">
        </div>
        <div class="form-group col-md-6">
            <label for="gearbox" class="form-label">{{ __('Gearbox') }}</label>
            <select name="gearbox" class="form-control hidesearch " id="gearbox" required>
                @foreach($gearbox as $k=>$val)
                    <option value="{{$k}}" {{ old('gearbox', $vehicle->gearbox) == $k ? 'selected' : '' }}>{{$val}}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="fuel_type" class="form-label">{{ __('Fuel Type') }}</label>
            <select name="fuel_type" class="form-control hidesearch " id="fuel_type" required>
                @foreach($fuelType as $k=>$val)
                    <option value="{{$k}}" {{ old('fuel_type', $vehicle->fuel_type) == $k ? 'selected' : '' }}>{{$val}}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="number_of_seats" class="form-label">{{ __('Number of Seats') }}</label>
            <input type="number" name="number_of_seats" id="number_of_seats" class="form-control" value="{{ old('number_of_seats', $vehicle->number_of_seats) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="kilometers" class="form-label">{{ __('Kilometer') }}</label>
            <input type="number" name="kilometers" id="kilometers" class="form-control" value="{{ old('kilometers', $vehicle->kilometers) }}" required>
        </div>
        <div class="form-group col-md-6">
            <label for="option" class="form-label">{{ __('Options') }}</label>
            <select name="option[]" id="option" class="form-control hidesearch " multiple>
                @foreach($option as $val => $label)
                    <option value="{{ $val }}" {{ in_array($val, old('option', [])) ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6">
            <label for="document" class="form-label">{{ __('Document') }}</label>
            <input type="file" name="document" id="document" class="form-control">
        </div>
        <div class="form-group col-md-6">
            <label for="picture" class="form-label">{{ __('Photo de voiture') }}</label>
            <input type="file" name="picture" id="picture" class="form-control">
        </div>
        <div class="form-group col-md-6">
            <label for="notes" class="form-label">{{ __('Notes') }}</label>
            <textarea name="notes" id="notes" class="form-control" placeholder="{{ __('Enter notes') }}" rows="1">{{ old('notes', $vehicle->notes) }}</textarea>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>
