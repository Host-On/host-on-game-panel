@extends('layouts.admin')

@section('title')
    Clusters
@endsection

@section('content-header')
    <h1>Proxmox Clusters<small>Logical clusters that group compute nodes.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Clusters</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Clusters</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Provider</th>
                            <th>Location</th>
                            <th class="text-center">Nodes</th>
                            <th class="text-center">Status</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($clusters as $cluster)
                            <tr>
                                <td>{{ $cluster->name }}</td>
                                <td>{{ $cluster->provider?->name ?? '-' }}</td>
                                <td>{{ $cluster->location?->short ?? '-' }}</td>
                                <td class="text-center">{{ $cluster->hosts_count }}</td>
                                <td class="text-center">
                                    <span class="label label-{{ $cluster->enabled ? 'success' : 'danger' }}">{{ $cluster->enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.clusters.delete', $cluster->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Delete this cluster?');">
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
                <h3 class="box-title">Add Cluster</h3>
            </div>
            <form action="{{ route('admin.hoston.clusters') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Host-On FRA Games">
                    </div>
                    <div class="form-group">
                        <label>Provider</label>
                        <select name="provider_id" class="form-control" required>
                            <option value="">- Select -</option>
                            @foreach ($providers as $provider)
                                <option value="{{ $provider->id }}">{{ $provider->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location_id" class="form-control">
                            <option value="">- None -</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->short }} ({{ $location->long }})</option>
                            @endforeach
                        </select>
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
