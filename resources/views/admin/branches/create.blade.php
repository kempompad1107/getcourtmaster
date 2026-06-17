@extends('layouts.app')
@section('title', 'Add Branch')

@section('content')

<x-page-header title="Add New Branch" :back="route('admin.branches.index')"/>

<div class="row justify-content-center">
<div class="col-12 col-lg-9 col-xl-8">

<form method="POST" action="{{ route('admin.branches.store') }}">
    @csrf
    @include('admin.branches._form', ['submitLabel' => 'Create Branch'])
</form>

</div>
</div>

@endsection
