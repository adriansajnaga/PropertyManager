@extends('layouts.app')

@section('title', 'Nowy rachunek')
@section('heading', 'Nowy rachunek za media')

@section('content')
    <flux:card class="max-w-3xl">
        <form method="POST" action="{{ route('utility-bills.store') }}" class="space-y-6">
            @include('utility-bills.form')
        </form>
    </flux:card>
@endsection
