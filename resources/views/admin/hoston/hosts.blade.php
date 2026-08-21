@extends('layouts.admin')

@section('title')
    Hypervisor Hosts
@endsection

@section('content-header')
    <h1>Hypervisor Hosts<small>Automatically synchronized from your Proxmox clusters.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Hypervisor Hosts</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="alert alert-info">
            Hosts are discovered and updated automatically from Proxmox
            (on cluster creation and every 5 minutes). They cannot be created
            or deleted manually here. Use
            <a href="{{ route('admin.hoston.clusters') }}">Proxmox Clusters</a>
            to manage connections.
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Synced Hosts</h3>
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
                            <th class="text-center">Weight</th>
                            <th class="text-center">Status</th>
                            <th>Last Synced</th>
                        </tr>
                        @foreach ($hosts as $host)
                            <tr>
                                <td><code>{{ $host->name }}</code></td>
                                <td>{{ $host->cluster?->name ?? '-' }}</td>
                                <td>{{ $host->location?->short ?? '-' }}</td>
                                <td class="text-center">{{ $host->cpu_cores }}</td>
                                <td class="text-center">{{ number_format($host->max_memory / 1024, 0) }} GB</td>
                                <td class="text-center">{{ number_format($host->max_disk, 0) }} GB</td>
                                <td class="text-center">{{ $host->placement_weight }}</td>
                                <td class="text-center">
                                    @if ($host->maintenance_mode)
                                        <span class="label label-warning">Maintenance</span>
                                    @elseif (!$host->enabled)
                                        <span class="label label-danger">Disabled</span>
                                    @elseif ($host->status === 'offline')
                                        <span class="label label-danger">Offline</span>
                                    @else
                                        <span class="label label-success">{{ $host->status ?? 'Healthy' }}</span>
                                    @endif
                                </td>
                                <td>{{ $host->last_synced_at?->diffForHumans() ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
