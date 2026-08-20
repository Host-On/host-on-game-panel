@extends('layouts.admin')

@section('title')
    Proxmox Providers
@endsection

@section('content-header')
    <h1>Proxmox Providers<small>Configure infrastructure provider connections (Proxmox VE / Demo).</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Providers</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Providers</h3>
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
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($providers as $provider)
                            <tr>
                                <td>{{ $provider->name }}</td>
                                <td><span class="label label-{{ $provider->type === 'proxmox' ? 'primary' : 'info' }}">{{ $provider->type }}</span></td>
                                <td><code>{{ $provider->api_url ?? '-' }}</code></td>
                                <td>{{ $provider->location?->short ?? '-' }}</td>
                                <td>
                                    @if ($provider->status === 'healthy')
                                        <span class="label label-success">Healthy</span>
                                    @elseif ($provider->status === 'error')
                                        <span class="label label-danger">Error</span>
                                    @else
                                        <span class="label label-default">Unknown</span>
                                    @endif
                                </td>
                                <td class="text-center">{{ $provider->tls_verify ? 'Yes' : 'No' }}</td>
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.providers.test', $provider->id) }}" method="POST" style="display:inline">
                                        @csrf
                                        <button class="btn btn-xs btn-primary">Test</button>
                                    </form>
                                    <form action="{{ route('admin.hoston.providers.sync-hosts', $provider->id) }}" method="POST" style="display:inline">
                                        @csrf
                                        <button class="btn btn-xs btn-info">Sync Hosts</button>
                                    </form>
                                    <button class="btn btn-xs btn-info" data-toggle="modal" data-target="#editProviderModal-{{ $provider->id }}">Edit</button>
                                    <form action="{{ route('admin.hoston.providers.delete', $provider->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Delete this provider?');">
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
                <h3 class="box-title">Add Provider</h3>
            </div>
            <form action="{{ route('admin.hoston.providers') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Host-On FRA Games">
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
                        <label>API Token User</label>
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
                    <button class="btn btn-success">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach ($providers as $provider)
<div class="modal fade" id="editProviderModal-{{ $provider->id }}" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.hoston.providers.update', $provider->id) }}" method="POST">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Edit Provider</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" value="{{ $provider->name }}" required>
                    </div>
                    <div class="form-group">
                        <label>Type</label>
                        <select name="type" class="form-control">
                            <option value="proxmox" {{ $provider->type === 'proxmox' ? 'selected' : '' }}>Proxmox VE</option>
                            <option value="fake" {{ $provider->type === 'fake' ? 'selected' : '' }}>Demo / Fake</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>API URL</label>
                        <input type="text" name="api_url" class="form-control" value="{{ $provider->api_url }}">
                    </div>
                    <div class="form-group">
                        <label>API Token User</label>
                        <input type="text" name="auth_user" class="form-control" value="{{ $provider->auth_user }}">
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
                                <option value="{{ $location->id }}" {{ $provider->location_id == $location->id ? 'selected' : '' }}>{{ $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="tls_verify" {{ $provider->tls_verify ? 'checked' : '' }}> Verify TLS certificate</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="enabled" {{ $provider->enabled ? 'checked' : '' }}> Enabled</label>
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
