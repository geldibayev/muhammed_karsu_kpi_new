@extends('layouts.app')

@section('content')
    <x-rating-list
        :$departments
        :$faculties
        :$positions
        :$filters
        :$mode
        :$report
        :$unitRankings
        :$users
        export-route="ratings.export"
        filter-route="ratings.index"
        :show-actions="true"
    />
@endsection
