@extends('layouts.admin')

@section('title')
    Hypervisor Hosts
@endsection

@section('content-header')
    <h1>Hypervisor Hosts<small>Proxmox hypervisors available for placement.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Hypervisor Hosts</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Hypervisor Hosts</h3>
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
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($hosts as $host)
                            <tr>
                                <td><code>{{ $host->name }}</code></td>
                                <td>{{ $host->cluster?->name ?? '-' }}</td>
                                <td>{{ $host->location?->short ?? '-' }}</td>
                                <td class="text-center">{{ $host->cpu_cores }}</td>
                                <td class="text-center">{{ number_format($host->max_memory / 1024, 0) }} GB</td>
                                <td class="text-center">{{ number_format($host->max_disk / 1024, 0) }} GB</td>
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
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.hosts.delete', $host->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Delete this host?');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-xs btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xs-12 col-md-6">
        <div class="box box-success">
            <div class="box-header with-border">
                <h3 class="box-title">Add Hypervisor Host</h3>
            </div>
            <form action="{{ route('admin.hoston.hosts') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name (Proxmox node name)</label>
                        <input type="text" name="name" class="form-control" required placeholder="game-pve01">
                    </div>
                    <div class="form-group">
                        <label>Hostname</label>
                        <input type="text" name="hostname" class="form-control" placeholder="game-pve01.host-on.internal">
                    </div>
                    <div class="form-group">
                        <label>Cluster</label>
                        <select name="cluster_id" class="form-control" required>
                            <option value="">- Select -</option>
                            @foreach ($clusters as $cluster)
                                <option value="{{ $cluster->id }}">{{ $cluster->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location_id" class="form-control">
                            <option value="">- None -</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>CPU Cores</label>
                                <input type="number" name="cpu_cores" class="form-control" required min="1" value="32">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>RAM (GB)</label>
                                <input type="number" name="memory_gb" class="form-control" required min="1" value="128">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Disk (GB)</label>
                                <input type="number" name="disk_gb" class="form-control" required min="1" value="2000">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Placement Weight</label>
                                <input type="number" name="placement_weight" class="form-control" min="0" value="100">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Reserved RAM (GB)</label>
                                <input type="number" name="reserved_memory_gb" class="form-control" min="0" value="0">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Reserved Disk (GB)</label>
                                <input type="number" name="reserved_disk_gb" class="form-control" min="0" value="0">
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Allowed Product Classes (comma separated)</label>
                        <input type="text" name="allowed_product_classes" class="form-control" placeholder="dedicated_vm, shared">
                        <p class="help-block">Leave empty to allow all classes. Classes: dedicated_vm, shared, static.</p>
                    </div>
                </div>
                <div class="box-footer">
                    <button class="btn btn-success">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
