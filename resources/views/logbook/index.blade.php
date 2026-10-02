@extends('layouts.app')
@section('title', 'Logbook Bimbingan')
@section('content')
@if (auth()->user()->isMahasiswa())
    @include('logbook.partials.student-history')
@else
    @include('logbook.partials.lecturer-history')
@endif
@endsection
