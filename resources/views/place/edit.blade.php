<form action="{{ route('place.update', $place->id) }}" method="POST">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="name" class="form-label">{{ __('Name') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter place name') }}" value="{{ old('name', $place->name) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="city" class="form-label">{{ __('City') }}</label>
            <input type="text" name="city" id="city" class="form-control" placeholder="{{ __('Enter city') }}" value="{{ old('city', $place->city) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="island" class="form-label">{{ __('Island') }}</label>
            <input type="text" name="island" id="island" class="form-control" placeholder="{{ __('Enter island') }}" value="{{ old('island', $place->island) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="price" class="form-label">{{ __('Price') }}</label>
            <input type="number" name="price" id="price" class="form-control" placeholder="{{ __('Enter price') }}" value="{{ old('price', $place->price) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="depo_name" class="form-label">{{ __('Depo name') }}</label>
            <input type="text" name="depo_name" id="depo_name" class="form-control" placeholder="{{ __('Enter depo name') }}" value="{{ old('depo_name', $place->depo_name) }}">
        </div>
        <div class="form-group col-md-12">
            <label for="depo_address" class="form-label">{{ __('Depo address') }}</label>
            <input type="text" name="depo_address" id="depo_address" class="form-control" placeholder="{{ __('Enter depo address') }}" value="{{ old('depo_address', $place->depo_address) }}">
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>
