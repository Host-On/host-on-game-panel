@extends('layouts.admin')

@section('title')
    Proxmox Clusters
@endsection

@section('content-header')
    <h1>Proxmox Clusters<small>Add a cluster, enter credentials, hosts are detected automatically.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Proxmox Clusters</li>
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
                            <th>Type</th>
                            <th>API URL</th>
                            <th>Location</th>
                            <th>Status</th>
                            <th class="text-center">TLS</th>
                            <th class="text-center">Hosts</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($clusters as $cluster)
                            <tr>
                                <td>{{ $cluster->name }}</td>
                                <td><span class="label label-{{ $cluster->type === 'proxmox' ? 'primary' : 'info' }}">{{ $cluster->type }}</span></td>
                                <td><code>{{ $cluster->api_url ?? '-' }}</code></td>
                                <td>{{ $cluster->location?->short ?? '-' }}</td>
                                <td>
                                    @if ($cluster->status === 'healthy')
                                        <span class="label label-success">Healthy</span>
                                    @elseif ($cluster->status === 'error')
                                        <span class="label label-danger">Error</span>
                                    @else
                                        <span class="label label-default">Unknown</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $cluster->tls_verify ? 'Yes' : 'No' }}</td>
                                <td class="text-center">{{ $cluster->hosts_count }}</td>
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.clusters.test', $cluster->id) }}" method="POST" style="display:inline">
                                        @csrf
                                        <button class="btn btn-xs btn-primary">Test</button>
                                    </form>
                                    <form action="{{ route('admin.hoston.clusters.sync-hosts', $cluster->id) }}" method="POST" style="display:inline">
                                        @csrf
                                        <button class="btn btn-xs btn-info">Sync Hosts</button>
                                    </form>
                                    <button class="btn btn-xs btn-info" data-toggle="modal" data-target="#editClusterModal-{{ $cluster->id }}">Edit</button>
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
                <h3 class="box-title">Add Proxmox Cluster</h3>
            </div>
            <form action="{{ route('admin.hoston.clusters.store') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="FRA Games Cluster">
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" class="form-control">
                            <option value="proxmox">Proxmox VE</option>
                            <option value="fake">Demo / Fake</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>API URL</label>
                        <input type="text" name="api_url" class="form-control" placeholder="https://pve.example.com:8006">
                    </div>
                    <div class="form-group">
                        <label>API Token ID</label>
                        <input type="text" name="auth_user" class="form-control" placeholder="user@pve!tokenid">
                    </div>
                    <div class="form-group">
                        <label>API Token Secret</label>
                        <input type="password" name="auth_token" class="form-control" autocomplete="new-password">
                        <p class="help-block">Stored encrypted. Never exposed to the frontend.</p>
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
                    <div class="checkbox">
                        <label><input type="checkbox" name="tls_verify" checked> Verify TLS certificate</label>
                    </div>
                </div>
                <div class="box-footer">
                    <button class="btn btn-success">Create Cluster</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach ($clusters as $cluster)
<div class="modal fade" id="editClusterModal-{{ $cluster->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.hoston.clusters.update', $cluster->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Edit Cluster</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="{{ $cluster->name }}" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" class="form-control">
                            <option value="proxmox" {{ $cluster->type === 'proxmox' ? 'selected' : '' }}>Proxmox VE</option>
                            <option value="fake" {{ $cluster->type === 'fake' ? 'selected' : '' }}>Demo / Fake</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>API URL</label>
                        <input type="text" name="api_url" class="form-control" value="{{ $cluster->api_url }}">
                    </div>
                    <div class="form-group">
                        <label>API Token ID</label>
                        <input type="text" name="auth_user" class="form-control" value="{{ $cluster->auth_user }}">
                    </div>
                    <div class="form-group">
                        <label>API Token Secret</label>
                        <input type="password" name="auth_token" class="form-control" autocomplete="new-password" placeholder="Leave empty to keep current">
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location_id" class="form-control">
                            <option value="">- None -</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}" {{ $cluster->location_id == $location->id ? 'selected' : '' }}>{{ $cluster->short ?? $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="tls_verify" {{ $cluster->tls_verify ? 'checked' : '' }}> Verify TLS certificate</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="enabled" {{ $cluster->enabled ? 'checked' : '' }}> Enabled</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endforeach
@endsection
