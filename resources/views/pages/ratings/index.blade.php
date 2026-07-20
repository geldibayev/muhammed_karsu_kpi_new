@extends('layouts.app')

@section('content')
    <x-rating-list
        :$departments
        :$faculties
        :$filters
        :$report
        :$users
        filter-route="ratings.index"
        :show-actions="true"
    />
@endsection
