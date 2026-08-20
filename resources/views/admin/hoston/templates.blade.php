@extends('layouts.admin')

@section('title')
    VM Templates
@endsection

@section('content-header')
    <h1>VM Templates<small>Cloud-Init enabled Proxmox templates used for provisioning.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">VM Templates</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Templates</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>Provider</th>
                            <th>Cluster</th>
                            <th class="text-center">Template VMID</th>
                            <th>Storage</th>
                            <th>Bridge</th>
                            <th class="text-center">Cloud-Init</th>
                            <th class="text-center">Wings Bootstrap</th>
                            <th class="text-right">Actions</th>
                        </tr>
                        @foreach ($templates as $template)
                            <tr>
                                <td>{{ $template->name }}</td>
                                <td>{{ $template->provider?->name ?? '-' }}</td>
                                <td>{{ $template->cluster?->name ?? '-' }}</td>
                                <td class="text-center"><code>{{ $template->template_vmid }}</code></td>
                                <td><code>{{ $template->storage }}</code></td>
                                <td><code>{{ $template->bridge }}</code></td>
                                <td class="text-center">
                                    <span class="label label-{{ $template->cloud_init_enabled ? 'success' : 'default' }}">{{ $template->cloud_init_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="label label-{{ $template->wings_bootstrap_enabled ? 'success' : 'default' }}">{{ $template->wings_bootstrap_enabled ? 'Enabled' : 'Disabled' }}</span>
                                </td>
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.templates.delete', $template->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Delete this template?');">
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
                <h3 class="box-title">Add VM Template</h3>
            </div>
            <form action="{{ route('admin.hoston.templates') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Debian 13 Game Node">
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
                        <label>Cluster</label>
                        <select name="cluster_id" class="form-control">
                            <option value="">- None -</option>
                            @foreach ($clusters as $cluster)
                                <option value="{{ $cluster->id }}">{{ $cluster->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Template VMID</label>
                        <input type="number" name="template_vmid" class="form-control" required min="1" value="9001">
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Storage</label>
                                <input type="text" name="storage" class="form-control" required value="local-zfs">
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Bridge</label>
                                <input type="text" name="bridge" class="form-control" required value="vmbr0">
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Default CPU</label>
                                <input type="number" name="default_cpu" class="form-control" value="4">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Default RAM (GB)</label>
                                <input type="number" name="default_memory_gb" class="form-control" value="8">
                            </div>
                        </div>
                        <div class="col-xs-4">
                            <div class="form-group">
                                <label>Default Disk (GB)</label>
                                <input type="number" name="default_disk" class="form-control" value="40">
                            </div>
                        </div>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="cloud_init_enabled" checked> Cloud-Init enabled</label>
                    </div>
                    <div class="checkbox">
                        <label><input type="checkbox" name="wings_bootstrap_enabled" checked> Wings bootstrap enabled</label>
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
