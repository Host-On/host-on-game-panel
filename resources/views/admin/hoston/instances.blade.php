@extends('layouts.admin')

@section('title')
    Customer VMs
@endsection

@section('content-header')
    <h1>Customer VMs<small>Dedicated VMs created automatically per game order.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.hoston.index') }}">Infrastructure</a></li>
        <li class="active">Customer VMs</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Instances</h3>
            </div>
            <div class="box-body table-responsive no-padding">
                <table class="table table-hover">
                    <tbody>
                        <tr>
                            <th>Name</th>
                            <th>VMID</th>
                            <th>Status</th>
                            <th>Public IP</th>
                            <th>Hypervisor</th>
                            <th>Customer</th>
                            <th>Wings Node</th>
                            <th>Created</th>
                        </tr>
                        @foreach ($instances as $instance)
                            <tr>
                                <td>{{ $instance->name }}</td>
                                <td><code>{{ $instance->vmid ?? '-' }}</code></td>
                                <td>
                                    @php $cls = match($instance->status) { 'running' => 'success', 'failed' => 'danger', 'terminated' => 'default', default => 'warning' }; @endphp
                                    <span class="label label-{{ $cls }}">{{ $instance->status }}</span>
                                </td>
                                <td><code>{{ $instance->game_ip ?? '-' }}</code></td>
                                <td>{{ $instance->host?->name ?? '-' }}</td>
                                <td>{{ $instance->customer?->email ?? '-' }}</td>
                                <td>{{ $instance->wings_node_id ?? '-' }}</td>
                                <td>{{ $instance->created_at?->diffForHumans() }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
