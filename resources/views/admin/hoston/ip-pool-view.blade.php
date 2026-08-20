@extends('layouts.admin')

@section('title')
    IP Pool: {{ $pool->name }}
@endsection

@section('content-header')
    <h1>{{ $pool->name }}<small>Manage public IP addresses for customer VMs.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li><a href="{{ route('admin.hoston.ip-pools') }}">IP Pools</a></li>
        <li class="active">{{ $pool->name }}</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-green"><i class="fa fa-check"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Available</span>
                <span class="info-box-number">{{ $available }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-blue"><i class="fa fa-cube"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Allocated</span>
                <span class="info-box-number">{{ $allocated }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-yellow"><i class="fa fa-lock"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Reserved</span>
                <span class="info-box-number">{{ $reserved }}</span>
            </div>
        </div>
    </div>
    <div class="col-xs-12 col-sm-6 col-md-3">
        <div class="info-box">
            <span class="info-box-icon bg-aqua"><i class="fa fa-network-wired"></i></span>
            <div class="info-box-content">
                <span class="info-box-text">Network</span>
                <span class="info-box-number"><small><code>{{ $pool->network }}</code></small></span>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Addresses</h3>
                <div class="box-tools">
                    <form action="{{ route('admin.hoston.ip-pools.sync', $pool->id) }}" method="POST" style="display:inline">
                        @csrf
                        <button class="btn btn-sm btn-info">Sync Range</button>
                    </form>
                    <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#addIpModal">Release IP</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Assigned To</th>
                            <th>Allocated At</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($allocations as $allocation)
                            <tr>
                                <td><code>{{ $allocation->address }}</code></td>
                                <td>
                                    @php $cls = $allocation->status === 'available' ? 'success' : ($allocation->status === 'reserved' ? 'warning' : 'primary'); @endphp
                                    <span class="label label-{{ $cls }}">{{ $allocation->status }}</span>
                                </td>
                                <td>
                                    @if ($allocation->instance)
                                        <a href="{{ route('admin.hoston.provisioning.view', $allocation->instance->id) }}">{{ $allocation->instance->name }}</a>
                                    @else
                                        -
                                    @endif
                                </td>
                                <td>{{ $allocation->allocated_at?->format('Y-m-d H:i') ?? '-' }}</td>
                                <td class="text-right">
                                    @if ($allocation->status === 'available')
                                        <form action="{{ route('admin.hoston.ip-pools.ips.reserve', [$pool->id, $allocation->id]) }}" method="POST" style="display:inline">
                                            @csrf
                                            <button class="btn btn-xs btn-warning">Reserve</button>
                                        </form>
                                    @else
                                        <form action="{{ route('admin.hoston.ip-pools.ips.release', [$pool->id, $allocation->id]) }}" method="POST" style="display:inline" onsubmit="return confirm('Release this IP address?');">
                                            @csrf
                                            <button class="btn btn-xs btn-success">Release</button>
                                        </form>
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

<div class="modal fade" id="addIpModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.hoston.ip-pools.ips', $pool->id) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Release a single IP address</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>IP Address</label>
                        <input type="text" name="address" class="form-control" required placeholder="203.0.113.50">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Release IP</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
