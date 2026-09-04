<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AddonController extends Controller
{

    public function index()
    {
        if (\Auth::user()->can('manage addon')) {
            $addons = Addon::where('parent_id', parentId())->get();
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }

        $addons->transform(function ($addon) {
            $addon->price_formatted = priceFormat($addon->price);
            return $addon;
        });
        return Inertia::render('Addon/Index', compact('addons'));
    }


    public function create()
    {
        $billingType=Addon::$billingType;

        return Inertia::render('Addon/Create', compact('billingType'));
    }


    public function store(Request $request)
    {
        if (\Auth::user()->can('create addon')) {
            $validator = \Validator::make(
                $request->all(), [
                    'name' => 'required',
                    'price' => 'required',
                    'billing_type' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $addon = new Addon();
            $addon->name = $request->name;
            $addon->price = $request->price;
            $addon->billing_type = $request->billing_type;
            $addon->parent_id = parentId();
            $addon->save();
            return redirect()->route('addon.index')->with('success', __('Addon successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function show(Addon $addon)
    {
        //
    }


    public function edit(Addon $addon)
    {
        $billingType=Addon::$billingType;

        return Inertia::render('Addon/Edit', compact('addon','billingType'));
    }


    public function update(Request $request, Addon $addon)
    {
        if (\Auth::user()->can('edit addon')) {
            $validator = \Validator::make(
                $request->all(), [
                    'name' => 'required',
                    'price' => 'required',
                    'billing_type' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            $addon->name = $request->name;
            $addon->price = $request->price;
            $addon->billing_type = $request->billing_type;
            $addon->save();
            return redirect()->route('addon.index')->with('success', __('Addon successfully updated.'));
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }


    public function destroy(Addon $addon)
    {
        if (\Auth::user()->can('delete addon') ) {
            $addon->delete();
            return redirect()->route('addon.index')->with('success', __('Addon successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function getAddonRateCalculation(Request $request)
    {
        $request->validate([
            'pickup_place'   => ['nullable', tenantPlaceRule()],
            'drop_off_place' => ['nullable', tenantPlaceRule()],
        ]);

        $addonAmount=0;
        $totalRate=0;
        $considerDays=1;
        $vehicle = Vehicle::find($request->vahicle_id);
        $start_date_time=$request->start_date_time;
        $end_date_time=$request->end_date_time;
        $daily_price = $request->daily_price;

        $pickup_place=$request->pickup_place;
        $drop_off_place=$request->drop_off_place;
        if(!empty($pickup_place)){
            $pickupPlaceAmount=placesRateCalculation($pickup_place);
        }else{
            $pickupPlaceAmount=0;
        }

        if(!empty($drop_off_place)){
            $dropPlaceAmount=placesRateCalculation($drop_off_place);
        }else{
            $dropPlaceAmount=0;
        }
        $placeAmount=$pickupPlaceAmount+$dropPlaceAmount;

        if (!empty($vehicle) && !empty($start_date_time) && !empty($end_date_time)) {
            $daily_rate = !empty($vehicle->daily_rate) && ($vehicle->daily_rate > 0) ? $vehicle->daily_rate : 0;
            $data=vehicleRateCalculation($daily_rate, $start_date_time, $end_date_time);
            $totalRate=(int)$data['totalRate'];
            $considerDays=$data['considerDays'];
            // if price day change
            if($request->daychange != 1){
                $data=vehicleRateCalculation($daily_rate, $start_date_time, $end_date_time);
                $totalRate=(int)$data['totalRate'];
            }else{ 
                $data = "" ;
                $totalRate = 0;
                $newdaily_rate = !empty($daily_price) && ($daily_price > 0) ? $daily_price : 0;
                $data = vehicleRateCalculation($newdaily_rate, $start_date_time, $end_date_time);
                $totalRate=(int)$data['totalRate'];
             }
        }

        if(!empty($request->addons)){
            $addonAmount =addonsRateCalculation($request->addons,$considerDays);
            $specificAddonCalculation =specificAddonCalculation($request->addons,$considerDays);
            $specificAddonString='';
            foreach ($specificAddonCalculation as $key => $value) {
                $specificAddonString.="<tr><td>".$value['addon']."</td><td>".$value['final_price']."</td></tr>";
            }
            $data['specificAddonCalculation']=$specificAddonString;
        }else{
            $data['specificAddonCalculation']= '';
        }
        


        $data['addonAmount']=$addonAmount;
        $data['totalRate']=$totalRate;
        $data['placeAmount']=$placeAmount;


        return json_encode($data);
    }
    public function getReductionRateCalculation(Request $request)
    {
        $request->validate([
            'pickup_place'   => ['nullable', tenantPlaceRule()],
            'drop_off_place' => ['nullable', tenantPlaceRule()],
        ]);

        $addonAmount=0;
        $totalRate=0;
        $considerDays=1;
        $vehicle = Vehicle::find($request->vahicle_id);
        $start_date_time=$request->start_date_time;
        $end_date_time=$request->end_date_time;


        $pickup_place=$request->pickup_place;
        $drop_off_place=$request->drop_off_place;
        if(!empty($pickup_place)){
            $pickupPlaceAmount=placesRateCalculation($pickup_place);
        }else{
            $pickupPlaceAmount=0;
        }

        if(!empty($drop_off_place)){
            $dropPlaceAmount=placesRateCalculation($drop_off_place);
        }else{
            $dropPlaceAmount=0;
        }
        $placeAmount=$pickupPlaceAmount+$dropPlaceAmount;

        if (!empty($vehicle) && !empty($start_date_time) && !empty($end_date_time)) {
            $daily_rate = !empty($vehicle->daily_rate) && ($vehicle->daily_rate > 0) ? $vehicle->daily_rate : 0;
            $data=vehicleRateCalculation($daily_rate, $start_date_time, $end_date_time);
            $totalRate=(int)$data['totalRate'];
            $considerDays=$data['considerDays'];
        }

        if(!empty($request->addons)){
            $addonAmount =addonsRateCalculation($request->addons,$considerDays);
            $specificAddonCalculation =specificAddonCalculation($request->addons,$considerDays);
            $specificAddonString='';
            foreach ($specificAddonCalculation as $key => $value) {
                $specificAddonString.="<tr><td>".$value['addon']."</td><td>".$value['final_price']."</td></tr>";
            }
            $data['specificAddonCalculation']=$specificAddonString;
        }
        $data['addonAmount']=$addonAmount;
        $data['totalRate']=$totalRate;
        $data['placeAmount']=$placeAmount;


        return json_encode($data);
    }
}
