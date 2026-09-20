@extends('layouts.app')

@section('title', 'Nowy odczyt')
@section('heading', 'Nowy odczyt')
@section('subheading', 'Ręczne wprowadzenie stanu licznika')

@section('content')
    <flux:card class="max-w-5xl">
        <form method="POST" action="{{ route('readings.store') }}" class="space-y-6">
            @include('readings.form')
        </form>
    </flux:card>
@endsection
