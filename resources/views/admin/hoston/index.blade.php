@extends('layouts.admin')

@section('title')
    Infrastructure
@endsection

@section('content-header')
    <h1>Infrastructure<small>Proxmox clusters, compute nodes and provisioning overview.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Infrastructure</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12 col-sm-6 col-md-2">
        <div class="info-box">
            <span class="info-box-icon bg-blue"><i class="fa fa-sitemap"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Clusters</span>
                <span class="info-box-number">{{ $clusterCount }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-2">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-server"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">PVE Nodes</span>
                <span class="info-box-number">{{ $hostCount }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-2">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-cubes"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Customer VMs</span>
                <span class="info-box-number">{{ $vmCount }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-2">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Healthy</span>
                <span class="info-box-number">{{ $healthyVm }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-2">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-spinner"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Provisioning</span>
                <span class="info-box-number">{{ $provisioning }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-2">
        <div class="info-box">
            <span class="info-box-icon bg-red"><i class="fa fa-times"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Failed</span>
                <span class="info-box-number">{{ $failed }}</span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Compute Nodes</h3>
                <div class="box-tools">
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.hoston.providers') }}">Providers</a>
                    <a class="btn btn-sm btn-primary" href="{{ route('admin.hoston.clusters') }}">Clusters</a>
                    <a class="btn btn-sm btn-info" href="{{ route('admin.hoston.hosts') }}">Compute Nodes</a>
                    <a class="btn btn-sm btn-info" href="{{ route('admin.hoston.templates') }}">VM Templates</a>
                    <a class="btn btn-sm btn-info" href="{{ route('admin.hoston.ip-pools') }}">IP Pools</a>
                    <a class="btn btn-sm btn-success" href="{{ route('admin.hoston.catalog') }}">Games & Products</a>
                    <a class="btn btn-sm btn-warning" href="{{ route('admin.hoston.provisioning') }}">Provisioning Jobs</a>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Host</th>
                            <th>Cluster</th>
                            <th class="text-center">CPU Cores</th>
                            <th class="text-center">CPU Load</th>
                            <th class="text-center">RAM (alloc/total)</th>
                            <th class="text-center">Storage</th>
                            <th class="text-center">Status</th>
                        </tr>
                        @foreach ($hosts as $host)
                            @php
                                $cpuPct = (int) $host->cpu_utilization;
                                $memFree = (int) (($host->max_memory - $host->allocated_memory) / $host->max_memory * 100);
                            @endphp
                            <tr>
                                <td><code>{{ $host->name }}</code></td>
                                <td>{{ $host->cluster?->name ?? '-' }}</td>
                                <td class="text-center">{{ $host->cpu_cores }}</td>
                                <td class="text-center">
                                    <div class="progress progress-xs" style="margin:0">
                                        <div class="progress-bar progress-bar-{{ $cpuPct > 80 ? 'danger' : ($cpuPct > 60 ? 'warning' : 'success') }}" style="width: {{ $cpuPct }}%"></div>
                                    </div>
                                    <small>{{ $cpuPct }}%</small>
                                </td>
                                <td class="text-center">{{ number_format($host->allocated_memory / 1024, 0) }} / {{ number_format($host->max_memory / 1024, 0) }} GB</td>
                                <td class="text-center">{{ $memFree }}% free</td>
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
