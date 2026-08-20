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
                            <th class="text-right">Actions</th>
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
                                <td class="text-right">
                                    <form action="{{ route('admin.hoston.ip-pools.delete', $pool->id) }}" method="POST" style="display:inline" onsubmit="return confirm('Delete this IP pool?');">
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
                <h3 class="box-title">Add IP Pool</h3>
            </div>
            <form action="{{ route('admin.hoston.ip-pools') }}" method="POST">
                @csrf
                <div class="box-body">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="Frankfurt Gaming Public">
                    </div>
                    <div class="form-group">
                        <label>Network (CIDR)</label>
                        <input type="text" name="network" class="form-control" required placeholder="203.0.113.0/24">
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Gateway</label>
                                <input type="text" name="gateway" class="form-control" placeholder="203.0.113.1">
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
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Allocation Start</label>
                                <input type="text" name="allocation_start" class="form-control" placeholder="203.0.113.10">
                            </div>
                        </div>
                        <div class="col-xs-6">
                            <div class="form-group">
                                <label>Allocation End</label>
                                <input type="text" name="allocation_end" class="form-control" placeholder="203.0.113.250">
                            </div>
                        </div>
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
