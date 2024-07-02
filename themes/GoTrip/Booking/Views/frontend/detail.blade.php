@extends('layouts.app')
@push('css')
    <link href="{{ asset('themes/gotrip/dist/frontend/module/booking/css/checkout.css?_ver='.config('app.asset_version')) }}" rel="stylesheet">
@endpush
@section('content')
    <div class="bravo-booking-page padding-content padding-content pt-40 pb-40" >
        <div class="container">
            @include ('Booking::frontend/global/booking-detail-notice')
            <div class="row booking-success-detail">
                <div class="col-md-8">
                    @include ($service->booking_customer_info_file ?? 'Booking::frontend/booking/booking-customer-info')
                    <div class="d-flex justify-content-center pt-sm-4 pt-md-0">
                        <div class="col-auto"></div>
                        <div class="col-auto">
                            <a href="{{route('user.booking_history')}}" class="button h-60 px-24 -dark-1 bg-blue-1 text-white">
                                {{__('Booking History')}}
                                <div class="icon-arrow-top-right ml-15"></div>
                            </a>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    @include ($service->checkout_booking_detail_file ?? '',['disable_lazyload'=>1])
                </div>
            </div>
        </div>
    </div>
@endsection
