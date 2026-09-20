@extends('layouts.app')

@section('title', 'Edycja rachunku')
@section('heading', 'Edycja rachunku')
@section('subheading', $bill->invoice_number)

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('utility-bills.update', $bill) }}" class="space-y-6">
            @method('PUT')
            @include('utility-bills.form')
        </form>
    </flux:card>
@endsection
