@extends('layouts.admin')

@section('title')
    Placement Simulator
@endsection

@section('content-header')
    <h1>Placement Simulator<small>See how the placement engine scores compute nodes.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Placement</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12 col-md-4">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Select Product</h3>
            </div>
            <form method="GET" action="{{ route('admin.hoston.placement') }}">
                <div class="box-body">
                    <div class="form-group">
                        <select name="profile" class="form-control" onchange="this.form.submit()">
                            <option value="">- Select a profile -</option>
                            @foreach ($profiles as $profile)
                                <option value="{{ $profile->slug }}" {{ request('profile') === $profile->slug ? 'selected' : '' }}>{{ $profile->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@if (!empty($ranked))
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Placement Results</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Host</th>
                            <th>Score</th>
                            <th>Reasons</th>
                            <th class="text-center">Result</th>
                        </tr>
                        @foreach ($ranked as $row)
                            <tr class="{{ !$row['excluded'] && $loop->first ? 'success' : '' }}">
                                <td><code>{{ $row['host']->name }}</code></td>
                                <td><strong>{{ $row['score'] }}</strong></td>
                                <td>{{ implode(' ', $row['reasons']) }}</td>
                                <td class="text-center">
                                    @if ($row['excluded'])
                                        <span class="label label-default">Excluded</span>
                                    @elseif ($loop->first)
                                        <span class="label label-success">Selected</span>
                                    @else
                                        <span class="label label-primary">Candidate</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endif
@endsection
