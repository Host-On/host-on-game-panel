@extends('layouts.admin')

@section('title')
    IP Pools
@endsection

@section('content-header')
    <h1>IP Pools<small>Public IP ranges for customer game servers.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">IP Pools</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">IP Pools</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Network</th>
                            <th>Gateway</th>
                            <th>Bridge</th>
                            <th>Location</th>
                            <th>Allocation Range</th>
                            <th class="text-center">Enabled</th>
                        </tr>
                        @foreach ($pools as $pool)
                            <tr>
                                <td>{{ $pool->name }}</td>
                                <td><code>{{ $pool->network }}</code></td>
                                <td><code>{{ $pool->gateway }}</code></td>
                                <td><code>{{ $pool->bridge }}</code></td>
                                <td>{{ $pool->location?->short ?? '-' }}</td>
                                <td>{{ $pool->allocation_start ?? '-' }} - {{ $pool->allocation_end ?? '-' }}</td>
                                <td class="text-center">
                                    <span class="label label-{{ $pool->enabled ? 'success' : 'default' }}">{{ $pool->enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
