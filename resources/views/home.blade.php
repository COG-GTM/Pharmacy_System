@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="d-flex justify-content-center">
                <div class="col-8 d-flex flex-column justify-content-center align-items-center rounded-4 p-5" style="letter-spacing: 1px;">
                    @if (session('status'))
                        <div class="alert alert-success" role="alert">
                            {{ session('status') }}
                        </div>
                    @endif
                    <div class="alert border-success fs-5" style="letter-spacing: 1px;">
                        {{ __('You are Logged in Successfully!') }}
                    </div>
                    @role('client')
                        <p class="mt-3 text-center">
                            {{ __('Place and track your orders from the Pharmacy System mobile app. Payment links from your order emails open here on the web.') }}
                        </p>
                    @endrole
                    <div class="mt-4">
                        <img src="dist/img/success-login.gif" alt="loginned successfully" width="150px"/>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

