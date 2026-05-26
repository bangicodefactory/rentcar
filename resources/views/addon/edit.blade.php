<form action="{{ route('addon.update', $addon->id) }}" method="POST">
@csrf
@method('PUT')
<div class="modal-body">
    <div class="row">
        <div class="form-group col-md-12">
            <label for="name" class="form-label">{{ __('Addon') }}</label>
            <input type="text" name="name" id="name" class="form-control" placeholder="{{ __('Enter addon name') }}" value="{{ old('name', $addon->name) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="price" class="form-label">{{ __('Price') }}</label>
            <input type="number" name="price" id="price" class="form-control" placeholder="{{ __('Enter price') }}" value="{{ old('price', $addon->price) }}" required>
        </div>
        <div class="form-group col-md-12">
            <label for="billing_type" class="form-label">{{ __('Billing Type') }}</label>
            <select name="billing_type" class="form-control hidesearch " id="billing_type">
                @foreach($billingType as $k=>$val)
                    <option value="{{$k}}">{{$val}}</option>
                @endforeach
            </select>
        </div>
    </div>
</div>
<div class="modal-footer">
    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">{{__('Close')}}</button>
    <button type="submit" class="btn btn-primary ml-10">{{__('Update')}}</button>
</div>
</form>


