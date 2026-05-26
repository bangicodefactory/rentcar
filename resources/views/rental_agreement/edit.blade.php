<form action="{{ route('rental-agreement.update', $rentalAgreement->id) }}" method="POST">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-6 col-lg-6">
            <label for="driver" class="form-label">{{ __('Driver') }}</label>
            <select name="driver" id="driver" class="form-control hidesearch " required>
                @foreach($drivers as $val => $label)
                    <option value="{{ $val }}" {{ old('driver', $rentalAgreement->driver) == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <label for="driver2" class="form-label">{{ __('Driver2') }}</label>
            <select name="driver2" id="driver2" class="form-control select2-search">
                {{-- <option value="">{{ __('Select Driver') }}</option> --}}
                @foreach ($drivers as $driverId => $driverName)
                    <option value="{{ $driverId }}" {{ $driver2 == $driverId ? 'selected' : '' }}>
                        {{ $driverName }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <label for="vehicle" class="form-label">{{ __('Vehicle') }}</label>
            <select name="vehicle" id="vehicle" class="form-control basic-select" required>
                <option value="">{{__('Select Vehicle')}}</option>
                @foreach($vehicles as $vehicle)
                    <option
                        value="{{$vehicle->id}}" {{$rentalAgreement->vehicle==$vehicle->id?'selected':''}}>{{$vehicle->name.' - '.$vehicle->license_plate}}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group col-md-6 col-lg-6">
            <label for="rental_start_date" class="form-label">{{ __('Rental Start Date') }}</label>
            <div class="d-flex">
                <input type="date" name="rental_start_date" id="rental_start_date" class="form-control" value="{{ old('rental_start_date', date('Y-m-d', strtotime($rentalAgreement->rental_start_date))) }}" required>
                <input type="time" name="rental_start_time" id="rental_start_time" class="form-control ms-2" value="{{ old('rental_start_time', date('H:i', strtotime($rentalAgreement->rental_start_date))) }}" required>
            </div>
        </div>

        <div class="form-group col-md-6 col-lg-6">
            <label for="rental_end_date" class="form-label">{{ __('Rental End Date') }}</label>
            <div class="d-flex">
                <input type="date" name="rental_end_date" id="rental_end_date" class="form-control" value="{{ old('rental_end_date', date('Y-m-d', strtotime($rentalAgreement->rental_end_date))) }}" required>
                <input type="time" name="rental_end_time" id="rental_end_time" class="form-control ms-2" value="{{ old('rental_end_time', date('H:i', strtotime($rentalAgreement->rental_end_date))) }}" required>
            </div>
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <label for="rental_duration" class="form-label">{{ __('Rental Duration (Days)') }}</label>
            <input type="number" name="rental_duration" id="rental_duration" class="form-control" placeholder="{{ __('Enter rental duration') }}" value="{{ old('rental_duration', $rentalAgreement->rental_duration) }}" required>
        </div>
        <div class="form-group col-md-6 col-lg-6">
            <label for="status" class="form-label">{{ __('Status') }}</label>
            <select name="status" id="status" class="form-control hidesearch " required>
                @foreach($status as $val => $label)
                    <option value="{{ $val }}" {{ old('status', $rentalAgreement->status) == $val ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group col-md-12 col-lg-12">
            <label for="terms_condition" class="form-label">{{ __('Terms & Condition') }}</label>
            <textarea name="terms_condition" id="terms_condition" class="form-control" placeholder="{{ __('Enter terms & condition') }}" rows="6">{{ old('terms_condition', $rentalAgreement->terms_condition) }}</textarea>
        </div>
        <div class="form-group col-md-12 col-lg-12">
            <label for="description" class="form-label">{{ __('Description') }}</label>
            <textarea name="description" id="description" class="form-control" placeholder="{{ __('Enter description') }}" rows="5">{{ old('description', $rentalAgreement->description) }}</textarea>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>


