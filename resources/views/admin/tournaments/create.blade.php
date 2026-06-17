@extends('layouts.app')
@section('title', 'New Tournament')

@section('content')

<x-page-header title="New Tournament" :back="route('admin.tournaments.index')"/>

<div class="row justify-content-center">
    <div class="col-12 col-lg-10 col-xl-9">
        <form method="POST" action="{{ route('admin.tournaments.store') }}" enctype="multipart/form-data">
            @csrf
            @include('admin.tournaments._form', ['submitLabel' => 'Create Tournament'])
        </form>
    </div>
</div>

@endsection
