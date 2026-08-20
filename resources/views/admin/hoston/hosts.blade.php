@extends('layouts.admin')

@section('title')
    Compute Nodes
@endsection

@section('content-header')
    <h1>Compute Nodes<small>Proxmox hypervisors available for placement.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Compute Nodes</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Nodes</h3>
                <div class="box-tools">
                    <a href="{{ route('admin.hoston.placement') }}" class="btn btn-sm btn-info">Placement Simulator</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Cluster</th>
                            <th>Location</th>
                            <th class="text-center">Cores</th>
                            <th class="text-center">RAM</th>
                            <th class="text-center">Disk</th>
                            <th class="text-center">CPU Load</th>
                            <th class="text-center">Status</th>
                        </tr>
                        @foreach ($hosts as $host)
                            <tr>
                                <td><code>{{ $host->name }}</code></td>
                                <td>{{ $host->cluster?->name ?? '-' }}</td>
                                <td>{{ $host->location?->short ?? '-' }}</td>
                                <td class="text-center">{{ $host->cpu_cores }}</td>
                                <td class="text-center">{{ number_format($host->max_memory / 1024, 0) }} GB</td>
                                <td class="text-center">{{ number_format($host->max_disk / 1024, 0) }} GB</td>
                                <td class="text-center">{{ (int) $host->cpu_utilization }}%</td>
                                <td class="text-center">
                                    @if ($host->maintenance_mode)
                                        <span class="label label-warning">Maintenance</span>
                                    @elseif (!$host->enabled)
                                        <span class="label label-danger">Disabled</span>
                                    @else
                                        <span class="label label-success">Healthy</span>
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
@endsection
