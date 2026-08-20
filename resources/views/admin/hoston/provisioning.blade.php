@extends('layouts.admin')

@section('title')
    Provisioning Jobs
@endsection

@section('content-header')
    <h1>Provisioning Jobs<small>Track the state machine that builds customer game servers.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Provisioning Jobs</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Jobs</h3>
                <div class="box-tools">
                    <button class="btn btn-sm btn-success" data-toggle="modal" data-target="#newServiceModal">Create Service</button>
                </div>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Job</th>
                            <th>Customer</th>
                            <th>Product</th>
                            <th>Status</th>
                            <th>Host</th>
                            <th>VMID</th>
                            <th>Server</th>
                            <th>Created</th>
                        </tr>
                        @foreach ($jobs as $job)
                            <tr>
                                <td><a href="{{ route('admin.hoston.provisioning.view', $job->id) }}"><code>{{ $job->external_id }}</code></a></td>
                                <td>{{ $job->user?->username ?? '-' }}</td>
                                <td>{{ $job->profile?->name ?? '-' }}</td>
                                <td>
                                    @php $cls = match($job->status) { 'completed' => 'success', 'failed' => 'danger', 'running' => 'info', 'waiting_for_bootstrap' => 'info', default => 'warning' }; @endphp
                                    <span class="label label-{{ $cls }}">{{ $job->status }}</span>
                                </td>
                                <td>{{ $job->host?->name ?? '-' }}</td>
                                <td>{{ $job->vmid ?? '-' }}</td>
                                <td>{{ $job->server_id ?? '-' }}</td>
                                <td>{{ $job->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="box-footer">
                {{ $jobs->links() }}
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="newServiceModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form action="{{ route('admin.hoston.services.create') }}" method="POST">
                @csrf
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                    <h4 class="modal-title">Create Game Service</h4>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Customer</label>
                        <select name="user_id" class="form-control" required>
                            @foreach (\Pterodactyl\Models\User::orderBy('username')->limit(500)->get() as $user)
                                <option value="{{ $user->id }}">{{ $user->username }} ({{ $user->email }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Product</label>
                        <select name="product" class="form-control" required>
                            @foreach (\Pterodactyl\Models\ResourceProfile::where('enabled', true)->get() as $profile)
                                <option value="{{ $profile->slug }}">{{ $profile->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Location</label>
                        <select name="location" class="form-control">
                            <option value="">Default</option>
                            @foreach (\Pterodactyl\Models\Location::all() as $location)
                                <option value="{{ $location->short }}">{{ $location->short }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Name (optional)</label>
                        <input type="text" name="name" class="form-control" placeholder="Minecraft Survival">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                    <button class="btn btn-success">Provision</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
